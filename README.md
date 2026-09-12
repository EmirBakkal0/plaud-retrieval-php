# Plaud PHP SDK (`plaud/plaud-php`)

An independent, reusable, and framework-agnostic PHP client for the [Plaud.ai](https://www.plaud.ai/) API.

Retrieve summaries, manage recordings, download audio, and access account metadata with **zero database/storage dependencies** and **zero mandatory PHP frameworks**.

---

## Features

- **Framework Agnostic**: Pure PHP (PHP 8.1+) ready for Vanilla PHP, Laravel, Symfony, WordPress, Slim, or custom CLI tools.
- **Zero Database / Storage Dependencies**: Tokens are kept in memory by default (`InMemoryTokenStorage`). No database, Redis, or disk persistence required.
- **Pluggable Token Storage**: Implement `TokenStorageInterface` to optionally persist tokens in Redis, PSR-6/16 cache, files, or sessions.
- **Zero Mandatory HTTP Library**: Built-in `CurlHttpClient` works out of the box with standard PHP `ext-curl`. Optional `GuzzleHttpClient` adapter is also included.
- **Automatic Browser User-Agent**: Handles Plaud's anti-bot check (which blocks default HTTP client User-Agents with 403).
- **Automated Token Lifecycle**: Decodes JWT expiration timestamps without external dependencies and auto-refreshes tokens when within 30 days of expiry.
- **Dynamic Region Redirection**: Automatically switches between `us` (`https://api.plaud.ai`) and `eu` (`https://api-euc1.plaud.ai`) on `-302` region responses.
- **Complete Endpoints**:
  - `getSummary($id)`: Standard summary text.
  - `getCustomSummary($id)`: Custom-template summary, or `null` when unavailable.
  - `getRecording($id)`: Full metadata, standard summary, and custom-template summary.
  - `listRecordings()`: List active recordings (automatically filters trash).
  - `downloadAudio($id)` / `saveAudioToFile($id, $path)`: Download raw audio stream.
  - `getMp3Url($id)`: Temporary MP3 signed URL.
  - `getUserInfo()`: User details and subscription membership.

---

## Installation

```bash
composer require plaud/plaud-php
```

*Requirements: PHP >= 8.1, `ext-curl`, `ext-json`.*

---

## Quickstart

### 1. Retrieve a Summary (In 3 Lines)

```php
use Plaud\PlaudClient;

$client = PlaudClient::createWithCredentials('your-email@example.com', 'your-password', 'us');

// Retrieve standard summary as string
$summary = $client->getSummary('recording_id_123');
echo $summary;
```

---

### 2. Full Recording Detail & Metadata

```php
use Plaud\PlaudClient;

$client = PlaudClient::createWithCredentials('your-email@example.com', 'your-password', 'us');

$recording = $client->getRecording('recording_id_123');

echo "Title:       " . $recording->filename . "\n";
echo "Date:        " . $recording->getFormattedStartDate('Y-m-d H:i') . "\n";
echo "Duration:    " . $recording->getDurationMinutes() . " minutes\n";
echo "Summary:\n" . $recording->summary . "\n";

if ($recording->customSummary) {
    echo "Custom-template summary:\n" . $recording->customSummary . "\n";
}
```

---

### 3. List Recordings

```php
use Plaud\PlaudClient;

$client = PlaudClient::createWithCredentials('your-email@example.com', 'your-password', 'us');

$recordings = $client->listRecordings();

foreach ($recordings as $rec) {
    echo "{$rec->id} | {$rec->getFormattedStartDate()} | {$rec->getDurationMinutes()}m | {$rec->filename}\n";
}
```

---

### 4. Download Audio

```php
use Plaud\PlaudClient;

$client = PlaudClient::createWithCredentials('your-email@example.com', 'your-password');

// Option A: Save binary stream directly to a file
$client->saveAudioToFile('recording_id_123', __DIR__ . '/recording.mp3');

// Option B: Get temporary signed MP3 URL
$url = $client->getMp3Url('recording_id_123');
echo "Temporary MP3 URL: {$url}\n";
```

---

### 5. Using Existing Access Token (No Credentials Stored)

If you have an existing JWT token from Plaud and do not want to provide email/password:

```php
use Plaud\PlaudClient;

$client = PlaudClient::createWithToken('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...', 'us');
$summary = $client->getSummary('recording_id_123');
```

---

## Token Storage Customization (Optional)

By default, tokens are stored in memory (`InMemoryTokenStorage`) and do not persist across separate PHP processes. 

If you want to persist the token across CLI commands, in a file, or in Redis, implement `TokenStorageInterface`:

```php
use Plaud\Storage\TokenStorageInterface;
use Plaud\DTO\TokenData;

class RedisTokenStorage implements TokenStorageInterface
{
    public function __construct(private \Redis $redis) {}

    public function getToken(): ?TokenData
    {
        $data = $this->redis->get('plaud:token');
        return $data ? TokenData::fromArray(json_decode($data, true)) : null;
    }

    public function saveToken(TokenData $token): void
    {
        $this->redis->set('plaud:token', json_encode($token->toArray()));
    }

    public function clearToken(): void
    {
        $this->redis->del('plaud:token');
    }
}

// Pass your custom storage to the client
$client = PlaudClient::createWithCredentials(
    email: 'user@example.com',
    password: 'password',
    storage: new RedisTokenStorage($redis)
);
```

A built-in `FileTokenStorage` is also provided for local CLI file caching:
```php
use Plaud\Storage\FileTokenStorage;

$storage = new FileTokenStorage(sys_get_temp_dir() . '/plaud_token.json');
$client = PlaudClient::createWithCredentials('user@example.com', 'password', storage: $storage);
```

---

## Using with Guzzle (Optional)

If your project already uses Guzzle (`composer require guzzlehttp/guzzle`), you can pass `GuzzleHttpClient`:

```php
use Plaud\PlaudClient;
use Plaud\Http\GuzzleHttpClient;

$httpClient = new GuzzleHttpClient();
$client = PlaudClient::createWithCredentials(
    email: 'user@example.com',
    password: 'password',
    httpClient: $httpClient
);
```

---

## Error Handling

The package provides a structured hierarchy of exceptions:

```php
use Plaud\Exceptions\AuthenticationException;
use Plaud\Exceptions\NotFoundException;
use Plaud\Exceptions\ApiException;
use Plaud\Exceptions\PlaudException;

try {
    $detail = $client->getRecording('invalid_id');
} catch (AuthenticationException $e) {
    // Bad credentials, invalid or expired token
    echo "Auth error: " . $e->getMessage();
} catch (NotFoundException $e) {
    // Recording does not exist
    echo "Not found: " . $e->getMessage();
} catch (ApiException $e) {
    // API status or server error (e.g. HTTP 500)
    echo "API error [{$e->getStatusCode()}]: " . $e->getMessage();
} catch (PlaudException $e) {
    // General SDK error
    echo "SDK error: " . $e->getMessage();
}
```

---

## Summary naming migration (breaking DTO change)

- Previous `$recording->transcript` becomes `$recording->summary`.
- Previous `$recording->summary` becomes `$recording->customSummary`.
- Use `getSummary($id)` and `getCustomSummary($id)`. `getTranscript()`, `$recording->transcript`, and `hasTranscript()` remain deprecated aliases for the standard summary.
- Direct `RecordingDetail` constructor calls must rename the old `transcript:` argument to `summary:` and the old `summary:` argument to `customSummary:`. Positional content arguments retain their order.
- Update application JSON mappings and consumers together: `summary` now means the standard summary. This change should be released as a breaking version.

`hasSummary()` and `hasCustomSummary()` check for non-blank content. A missing standard summary is `''`; a missing custom-template summary is `null`.

This is a terminology change based on the existing integration's outputs, not a new verbatim-transcription feature. Upstream payload keys are unchanged: the standard summary uses the legacy `transcript` field and the longest `pre_download_content_list[].data_content`; the custom-template summary uses `summary`, falling back to `ai_summary`. Length does not identify content type; selection is preserved pending validation against real payloads. `raw`, `isTrans`, and `isSummary` retain their existing upstream semantics; use the new presence helpers to check SDK summary content.

The updated CLI example is `examples/01_get_summary.php`.

---

## Running Tests

```bash
./vendor/bin/phpunit
```

---

## License

MIT License. See [LICENSE](LICENSE) for details.
