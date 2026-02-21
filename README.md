# AISEOMeta

[English](README.md) | [简体中文](README.zh-hans.md)

AISEOMeta is a MediaWiki extension that automatically generates SEO Meta tags for Wiki pages using AI (Gemini or OpenAI) via Job Queue.

## Installation

1. Clone or download this repository into your `extensions/` directory:
   ```bash
   cd extensions/
   git clone https://github.com/yourusername/AISEOMeta.git
   ```

2. Install PHP dependencies using Composer:
   ```bash
   cd AISEOMeta
   composer install --no-dev
   ```

3. Add the following code at the bottom of your `LocalSettings.php`:
   ```php
   wfLoadExtension( 'AISEOMeta' );
   ```

4. Configure the extension in your `LocalSettings.php` (see Configuration section below).

5. Done! Navigate to Special:Version on your wiki to verify that the extension is successfully installed.

## Configuration

You can configure the extension by adding the following variables to your `LocalSettings.php`:

### General Settings
* `$wgASMProvider`: Choose the AI provider. Valid options are `'openai'` or `'gemini'`. Default is `'openai'`.
* `$wgASMPromptTemplate`: The prompt template sent to the AI. Use `{text}` as a placeholder for the page content.

### OpenAI Settings
* `$wgASMOpenAIEndpoint`: The endpoint for the OpenAI compatible API. Default is `'https://api.openai.com/v1/chat/completions'`.
* `$wgASMOpenAIKey`: Your OpenAI API Key.
* `$wgASMOpenAIModel`: The OpenAI model to use. Default is `'gpt-3.5-turbo'`.

### Gemini Settings
* `$wgASMGeminiKey`: Your Gemini API Key.
* `$wgASMGeminiModel`: The Gemini model to use. Default is `'gemini-2.0-flash'`.

### Example Configuration

```php
// Use OpenAI
$wgASMProvider = 'openai';
$wgASMOpenAIKey = 'your-openai-api-key';
$wgASMOpenAIModel = 'gpt-4-turbo';

// Or use Gemini
// $wgASMProvider = 'gemini';
// $wgASMGeminiKey = 'your-gemini-api-key';
// $wgASMGeminiModel = 'gemini-2.0-flash';
```

## Custom Tags

You can define custom meta tags that will be merged with the AI-generated tags. Create or edit the page `MediaWiki:ASM-custom-tags` on your wiki and add tags in the format `name|content`, one per line.

Example:
```text
author|Your Wiki Name
robots|index, follow
```

## Special Page Console

The extension provides a Special Page (`Special:AISEOMeta`) that acts as an administrative console. From this console, administrators can:
1. **View Current Configuration**: Check which AI provider is active and whether API keys are set (keys are masked for security).
2. **Query Page Status**: Enter a page title to see its current AI-generated SEO tags and the exact timestamp of when they were last generated.
3. **Manual Regeneration**: Push a specific page to the Job Queue for immediate regeneration directly from the query results.
4. **Batch Regeneration**: Enter multiple page titles (one per line) to push them all to the Job Queue at once.

To access the console, navigate to `Special:AISEOMeta` on your wiki.

## Maintenance Scripts

The extension provides maintenance scripts to manage the generated SEO tags. Run these scripts from your MediaWiki installation directory.

### CleanSEOMeta.php
Cleans AI-generated SEO meta tags from the `page_props` table.

* Clean all tags:
  ```bash
  php extensions/AISEOMeta/maintenance/CleanSEOMeta.php --all
  ```
* Clean tags for a specific page:
  ```bash
  php extensions/AISEOMeta/maintenance/CleanSEOMeta.php --page="Main Page"
  ```

### RegenerateSEOMeta.php
Regenerates AI SEO meta tags by pushing jobs to the queue.

* Regenerate for all pages in the main namespace:
  ```bash
  php extensions/AISEOMeta/maintenance/RegenerateSEOMeta.php --all
  ```
* Regenerate for a specific page:
  ```bash
  php extensions/AISEOMeta/maintenance/RegenerateSEOMeta.php --page="Main Page"
  ```
* Force regeneration even if tags already exist:
  ```bash
  php extensions/AISEOMeta/maintenance/RegenerateSEOMeta.php --all --force
  ```

## License

MIT License
