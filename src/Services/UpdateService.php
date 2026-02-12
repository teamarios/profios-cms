<?php

namespace App\Services;

use App\Models\Setting;

final class UpdateService
{
    public static function status(): array
    {
        $gitDir = BASE_PATH . '/.git';
        if (!is_dir($gitDir)) {
            return [
                'enabled' => false,
                'available' => false,
                'message' => 'Git repository not found in application root.',
            ];
        }

        try {
            $branch = trim((string) self::run(['git', 'rev-parse', '--abbrev-ref', 'HEAD'])['output']);
            $lastCommit = trim((string) self::run(['git', 'log', '-1', '--pretty=format:%h %s (%cr)'])['output']);
            $remote = trim((string) self::run(['git', 'remote', 'get-url', 'origin'])['output']);
        } catch (\Throwable $e) {
            return [
                'enabled' => setting('ops_updates_enabled', '0') === '1',
                'available' => false,
                'message' => $e->getMessage(),
            ];
        }

        return [
            'enabled' => setting('ops_updates_enabled', '0') === '1',
            'available' => true,
            'branch' => $branch,
            'last_commit' => $lastCommit,
            'remote' => $remote,
        ];
    }

    public static function saveConfig(array $payload): void
    {
        Setting::upsertMany([
            'ops_updates_enabled' => $payload['ops_updates_enabled'] ? '1' : '0',
            'ops_repo_url' => (string) $payload['ops_repo_url'],
            'ops_branch' => (string) $payload['ops_branch'],
            'ops_git_user_name' => (string) $payload['ops_git_user_name'],
            'ops_git_user_email' => (string) $payload['ops_git_user_email'],
        ]);
    }

    public static function pull(): array
    {
        self::assertEnabled();
        self::syncRemoteUrl();

        $branch = trim((string) setting('ops_branch', 'main')) ?: 'main';
        $results = [];
        $results[] = self::run(['git', 'fetch', '--all']);
        $results[] = self::run(['git', 'checkout', $branch]);
        $results[] = self::run(['git', 'pull', 'origin', $branch]);

        return self::flatten($results);
    }

    public static function push(string $message): array
    {
        self::assertEnabled();
        self::syncRemoteUrl();

        $branch = trim((string) setting('ops_branch', 'main')) ?: 'main';
        $gitName = trim((string) setting('ops_git_user_name', 'Profios CMS Bot'));
        $gitEmail = trim((string) setting('ops_git_user_email', 'noreply@example.com'));

        $msg = trim($message);
        if ($msg === '') {
            $msg = 'CMS automated update';
        }

        $results = [];
        $results[] = self::run(['git', 'config', 'user.name', $gitName]);
        $results[] = self::run(['git', 'config', 'user.email', $gitEmail]);
        $results[] = self::run(['git', 'add', '-A']);
        $results[] = self::run(['git', 'commit', '-m', $msg], true);
        $results[] = self::run(['git', 'push', 'origin', $branch]);

        return self::flatten($results);
    }

    private static function assertEnabled(): void
    {
        if (setting('ops_updates_enabled', '0') !== '1') {
            throw new \RuntimeException('Git updates are disabled. Enable them from Updates settings first.');
        }

        if (!is_dir(BASE_PATH . '/.git')) {
            throw new \RuntimeException('Git repository not available.');
        }
    }

    private static function syncRemoteUrl(): void
    {
        $url = trim((string) setting('ops_repo_url', ''));
        if ($url === '') {
            return;
        }

        self::run(['git', 'remote', 'set-url', 'origin', $url], true);
    }

    private static function run(array $parts, bool $allowFailure = false): array
    {
        $cmd = implode(' ', array_map('escapeshellarg', $parts));
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptor, $pipes, BASE_PATH);
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to execute command.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $code = proc_close($process);
        if ($code !== 0 && !$allowFailure) {
            throw new \RuntimeException(trim($stderr) !== '' ? trim($stderr) : trim($stdout));
        }

        return [
            'command' => implode(' ', $parts),
            'code' => $code,
            'output' => trim($stdout . "\n" . $stderr),
        ];
    }

    private static function flatten(array $results): array
    {
        $out = [];
        foreach ($results as $result) {
            $out[] = '[' . $result['command'] . '] code=' . $result['code'];
            if ($result['output'] !== '') {
                $out[] = $result['output'];
            }
        }

        return $out;
    }
}
