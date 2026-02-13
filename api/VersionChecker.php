<?php
/**
 * Version Checker - GitHub Releases API Integration
 *
 * Fetches version and release information from GitHub Releases.
 * Includes caching to minimize API calls.
 *
 * @package    Snip
 * @version    1.0.3
 */

class VersionChecker {
    private $cacheFile;
    private $cacheExpiry = 3600; // 1 hour

    public function __construct() {
        $this->cacheFile = dirname(__DIR__) . '/storage/cache/version-check.json';
        @mkdir(dirname($this->cacheFile), 0755, true);
    }

    /**
     * Get latest version from GitHub Releases
     */
    public function getLatestVersion(): string {
        $release = $this->getLatestRelease();
        return $release['tag_name'] ?? '1.0.0';
    }

    /**
     * Get release notes
     */
    public function getReleaseNotes(string $version): string {
        $release = $this->getRelease($version);
        return $release['body'] ?? '';
    }

    /**
     * Get SHA256 hash from release
     */
    public function getReleaseHash(string $version): ?string {
        $release = $this->getRelease($version);

        // Try to find hash in release notes
        if (isset($release['body'])) {
            if (preg_match('/SHA256:\s*([a-f0-9]{64})/i', $release['body'], $matches)) {
                return strtolower($matches[1]);
            }
        }

        // Look for hash in asset filename or description
        if (isset($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (preg_match('/\.sha256|\.checksum/i', $asset['name'])) {
                    return $this->fetchFileContent($asset['browser_download_url']);
                }
            }
        }

        return null;
    }

    /**
     * Get latest release from cache or GitHub API
     */
    private function getLatestRelease(): array {
        // Check cache first
        $cached = $this->getFromCache();
        if ($cached) {
            return $cached;
        }

        // Fetch from GitHub
        $release = $this->fetchFromGitHub('/releases/latest');

        if ($release) {
            $this->saveToCache($release);
        }

        return $release ?: [];
    }

    /**
     * Get specific release
     */
    private function getRelease(string $version): array {
        // Normalize version
        $version = ltrim($version, 'v');

        // Fetch from GitHub
        $release = $this->fetchFromGitHub('/releases/tags/v' . $version);

        return $release ?: [];
    }

    /**
     * Fetch from GitHub API
     */
    private function fetchFromGitHub(string $endpoint): ?array {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s%s',
            GITHUB_REPO_OWNER,
            GITHUB_REPO_NAME,
            $endpoint
        );

        $context = stream_context_create([
            'http' => [
                'timeout' => GITHUB_API_TIMEOUT,
                'user_agent' => 'SN/P-URL-Shortener/1.0'
            ],
            'https' => [
                'timeout' => GITHUB_API_TIMEOUT,
                'verify_peer' => false
            ]
        ]);

        try {
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return is_array($data) ? $data : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Fetch file content from URL
     */
    private function fetchFileContent(string $url): ?string {
        $context = stream_context_create([
            'http' => ['timeout' => GITHUB_API_TIMEOUT],
            'https' => ['timeout' => GITHUB_API_TIMEOUT, 'verify_peer' => false]
        ]);

        try {
            $content = @file_get_contents($url, false, $context);
            if ($content !== false) {
                // Extract first word (hash) if multiple lines
                $content = trim(explode("\n", $content)[0]);
                if (preg_match('/^[a-f0-9]{64}$/i', $content)) {
                    return strtolower($content);
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Get cached version info
     */
    private function getFromCache(): ?array {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $mtime = filemtime($this->cacheFile);
        if (time() - $mtime > $this->cacheExpiry) {
            @unlink($this->cacheFile);
            return null;
        }

        try {
            $data = json_decode(file_get_contents($this->cacheFile), true);
            return is_array($data) ? $data : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Save to cache
     */
    private function saveToCache(array $data): void {
        try {
            @mkdir(dirname($this->cacheFile), 0755, true);
            file_put_contents($this->cacheFile, json_encode($data));
        } catch (Exception $e) {
            // Ignore cache errors
        }
    }

    /**
     * Clear cache
     */
    public function clearCache(): void {
        @unlink($this->cacheFile);
    }
}
