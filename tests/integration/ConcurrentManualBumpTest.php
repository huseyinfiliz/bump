<?php

namespace HuseyinFiliz\Bump\Tests\Integration;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use HuseyinFiliz\Bump\BumpQuota;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ConcurrentManualBumpTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private ?string $fixture = null;

    public static function budgets(): array
    {
        return [
            'daily quota across discussions' => [1, 0, 0, false],
            'weekly quota across discussions' => [0, 1, 0, false],
            'cooldown on the same discussion' => [0, 0, 24, true],
        ];
    }

    #[Test]
    #[DataProvider('budgets')]
    public function concurrent_requests_consume_only_one_allowance(int $daily, int $weekly, int $cooldown, bool $sameDiscussion): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('The concurrency regression requires pcntl and posix.');
        }

        $config = require $this->tmpDir().'/config.php';

        if ($config['database']['driver'] !== 'sqlite') {
            $this->markTestSkipped('This isolated fixture uses SQLite; native MySQL/MariaDB concurrency is validated separately.');
        }

        // Never commit fixtures into the shared integration database.
        $this->fixture = tempnam(sys_get_temp_dir(), 'bump-concurrency-');
        $source = $config['database']['database'];
        copy(is_file($source) ? $source : $this->tmpDir().'/'.$source, $this->fixture);
        $this->config('database', array_replace($config['database'], ['database' => $this->fixture]));
        $this->extension('huseyinfiliz-bump', 'flarum-tags');
        $this->setting('huseyinfiliz-bump.owner-daily-quota', $daily);
        $this->setting('huseyinfiliz-bump.owner-weekly-quota', $weekly);
        $this->setting('huseyinfiliz-bump.manual-cooldown-hours', $cooldown);
        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            Discussion::class => [
                ['id' => 1, 'title' => 'Concurrent first discussion', 'user_id' => 2, 'comment_count' => 1, 'created_at' => Carbon::now()],
                ['id' => 2, 'title' => 'Concurrent second discussion', 'user_id' => 2, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
        ]);
        $this->app();
        $this->database()->commit();
        $this->database()->disconnect();
        $children = [];

        try {
            foreach ([1, $sameDiscussion ? 1 : 2] as $worker => $discussionId) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    throw new \RuntimeException('Could not start concurrency worker.');
                }

                if ($pid === 0) {
                    try {
                        touch($this->fixture.'.ready'.$worker);
                        $deadline = microtime(true) + 5;

                        while (! is_file($this->fixture.'.start')) {
                            if (microtime(true) > $deadline) {
                                throw new \RuntimeException('Concurrency start barrier timed out.');
                            }
                            usleep(1000);
                        }

                        $response = $this->send($this->request('POST', '/api/discussions/'.$discussionId.'/bump', ['authenticatedAs' => 2]));
                        file_put_contents($this->fixture.'.result'.$worker, json_encode(['status' => $response->getStatusCode()]));
                        exit(0);
                    } catch (\Throwable $e) {
                        file_put_contents($this->fixture.'.result'.$worker, json_encode(['error' => $e->getMessage()]));
                        exit(1);
                    }
                }

                $children[] = $pid;
            }

            $deadline = microtime(true) + 5;
            while (! is_file($this->fixture.'.ready0') || ! is_file($this->fixture.'.ready1')) {
                if (microtime(true) > $deadline) {
                    throw new \RuntimeException('Concurrency workers did not become ready.');
                }
                usleep(1000);
            }
            touch($this->fixture.'.start');

            foreach ($children as $pid) {
                $deadline = microtime(true) + 10;
                while (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
                    if (microtime(true) > $deadline) {
                        throw new \RuntimeException('Concurrency worker timed out.');
                    }
                    usleep(1000);
                }
                $this->assertSame(0, pcntl_wexitstatus($status));
            }
            $responses = [
                json_decode(file_get_contents($this->fixture.'.result0'), true),
                json_decode(file_get_contents($this->fixture.'.result1'), true),
            ];
            $statuses = array_column($responses, 'status');
            sort($statuses);
            $this->assertSame([200, 422], $statuses, json_encode($responses));
            $this->assertEquals(1, BumpQuota::count());
            $this->assertEquals(1, Discussion::whereNotNull('last_bumped_at')->count());
        } finally {
            foreach ($children as $pid) {
                if (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
                    posix_kill($pid, SIGKILL);
                    pcntl_waitpid($pid, $status);
                }
            }
        }
    }

    protected function tearDown(): void
    {
        // A skipped driver check has not booted the application.
        if ($this->fixture !== null) {
            parent::tearDown();
            $this->database()->disconnect();
            foreach (['', '-journal', '-wal', '-shm', '.ready0', '.ready1', '.start', '.result0', '.result1'] as $suffix) {
                if (is_file($this->fixture.$suffix)) {
                    unlink($this->fixture.$suffix);
                }
            }
        }
    }
}
