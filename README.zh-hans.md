# AISEOMeta

[English](README.md) | [简体中文](README.zh-hans.md)

AISEOMeta 是一个 MediaWiki 扩展，旨在利用 AI（Gemini 或 OpenAI 兼容 API）通过工作队列（Job Queue）自动为 Wiki 页面生成 SEO Meta 标签。

## 安装

1. 将此仓库克隆或下载到您的 `extensions/` 目录中：
   ```bash
   cd extensions/
   git clone https://github.com/yourusername/AISEOMeta.git
   ```

2. 使用 Composer 安装 PHP 依赖：
   ```bash
   cd AISEOMeta
   composer install --no-dev
   ```

3. 在您的 `LocalSettings.php` 文件底部添加以下代码：
   ```php
   wfLoadExtension( 'AISEOMeta' );
   ```

4. 在 `LocalSettings.php` 中配置该扩展（参见下文的“配置”部分）。

5. 完成！导航到您 Wiki 上的 Special:Version 页面，验证扩展是否已成功安装。

## 配置

您可以通过在 `LocalSettings.php` 中添加以下变量来配置扩展：

### 通用设置
* `$wgASMProvider`: 选择 AI 提供商。有效选项为 `'openai'` 或 `'gemini'`。默认值为 `'openai'`。
* `$wgASMPromptTemplate`: 发送给 AI 的提示词模板。使用 `{text}` 作为页面内容的占位符。

### OpenAI 设置
* `$wgASMOpenAIEndpoint`: OpenAI 兼容 API 的端点。默认值为 `'https://api.openai.com/v1/chat/completions'`。
* `$wgASMOpenAIKey`: 您的 OpenAI API 密钥。
* `$wgASMOpenAIModel`: 要使用的 OpenAI 模型。默认值为 `'gpt-3.5-turbo'`。

### Gemini 设置
* `$wgASMGeminiKey`: 您的 Gemini API 密钥。
* `$wgASMGeminiModel`: 要使用的 Gemini 模型。默认为 `'gemini-2.0-flash'`。

### 配置示例

```php
// 使用 OpenAI
$wgASMProvider = 'openai';
$wgASMOpenAIKey = 'your-openai-api-key';
$wgASMOpenAIModel = 'gpt-4-turbo';

// 或者使用 Gemini
// $wgASMProvider = 'gemini';
// $wgASMGeminiKey = 'your-gemini-api-key';
// $wgASMGeminiModel = 'gemini-2.0-flash';
```

## 自定义标签

您可以定义自定义的 Meta 标签，这些标签将与 AI 生成的标签合并。在您的 Wiki 上创建或编辑 `MediaWiki:ASM-custom-tags` 页面，并以 `name|content` 的格式添加标签，每行一个。

示例：
```text
author|Your Wiki Name
robots|index, follow
```

## 特殊页面控制台

该扩展提供了一个特殊页面（`Special:AISEOMeta`），作为管理控制台。管理员可以通过该控制台：
1. **查看当前配置**：检查当前激活的 AI 提供商以及是否设置了 API 密钥（出于安全考虑，密钥会被脱敏显示）。
2. **查询页面状态**：输入页面标题，查看其当前由 AI 生成的 SEO 标签以及确切的生成时间戳。
3. **手动重新生成**：直接从查询结果中将特定页面推送到工作队列，以便立即重新生成。
4. **批量重新生成**：输入多个页面标题（每行一个），一次性将它们全部推送到工作队列。

要访问控制台，请在您的 Wiki 上导航至 `Special:AISEOMeta`。

## 维护脚本

该扩展提供了维护脚本来管理生成的 SEO 标签。请从您的 MediaWiki 安装目录运行这些脚本。

### CleanSEOMeta.php
清理 `page_props` 表中由 AI 生成的 SEO Meta 标签。

* 清理所有标签：
  ```bash
  php extensions/AISEOMeta/maintenance/CleanSEOMeta.php --all
  ```
* 清理特定页面的标签：
  ```bash
  php extensions/AISEOMeta/maintenance/CleanSEOMeta.php --page="Main Page"
  ```

### RegenerateSEOMeta.php
通过将任务推入队列来重新生成 AI SEO Meta 标签。

* 为主命名空间中的所有页面重新生成：
  ```bash
  php extensions/AISEOMeta/maintenance/RegenerateSEOMeta.php --all
  ```
* 为特定页面重新生成：
  ```bash
  php extensions/AISEOMeta/maintenance/RegenerateSEOMeta.php --page="Main Page"
  ```
* 即使标签已存在也强制重新生成：
  ```bash
  php extensions/AISEOMeta/maintenance/RegenerateSEOMeta.php --all --force
  ```

## 许可证

MIT License
