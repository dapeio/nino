# Getting Started with Nino

**Language:** English · [Deutsch](getting-started.de.md)

**Last updated:** August 8, 2026 · **Nino version:** 0.11.0-beta.1

This guide leads you on the shortest path from a fresh checkout to a locally running Nino website. If you instead want to look up every field and writing process of the assistant, read the [`/_install` Reference](_install.md); technical backgrounds are explained in the [Concepts](concepts.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [`/_install` Reference](_install.md) · [`/_admin` Operation](_admin.md) · [`/_templates` Operation](_templates.md) · [`/_editor` Operation](_editor.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Important:** A fresh checkout contains the kernel, interfaces, and the installation library, but not yet a complete project state. `/_install` creates and fills the required project directories; only then does the website run.

## Prerequisites

For local setup, PHP 8.4 or newer, the PHP extensions checked by Nino, and write permissions in the project root are required. Git is necessary if the project is checked out directly from the repository.

The first installation step checks the version, extensions, and write permissions. Missing directories such as `templates/`, `text/`, `elements/`, or `images/` are expected at this point—PHP must only be able to create them.

> **Security:** Perform the setup locally or in another protected environment. Until completion, `/_install` has no individual access protection, and the real password for `/_admin` is not yet set.

## Check out the project and start

```bash
git clone https://github.com/dapeio/nino.git my-website
cd my-website
php -S 127.0.0.1:8000 router.php
```

Then open <http://127.0.0.1:8000/_install>. `router.php` maps the local routing; for production, a dedicated web server configuration is required.

## The Seven Steps

As long as the assistant is not completed, you can return to earlier steps and reapply settings. What is replaced, added, or preserved in the process is described in the [`/_install` Reference](_install.md#navigation-and-saving).

| Step | Decision |
|---|---|
| [1. Environment](_install.md#1-environment) | Are PHP, extensions, and write permissions ready for use? |
| [2. Setup](_install.md#2-setup) | Which languages and functional modules does the project require? |
| [3. Themes](_install.md#3-themes) | Which visual starting point should be copied? |
| [4. Webpages](_install.md#4-webpages) | Which first pages, public paths, and metadata are created? |
| [5. Personal Information](_install.md#5-personal-information) | Which central company and website values are available as textfills? |
| [6. Editor Access](_install.md#6-access-for-_editor) | Which first account receives full access to `/_editor`? |
| [7. Completion](_install.md#7-completion) | Which separate password protects `/_admin` and `/_templates` and locks the installer? |

The assistant automatically resolves dependencies between selected modules and the page templates used. The theme is a starting point for subsequent frontend development; after completion, no later theme change via `/_install` is planned.

The first editor account has full rights. Additional accounts are later created or deleted via `/_admin`; existing users manage their data and—with appropriate permissions—released rights in `/_editor`.

## Verify the Result

After completion, open:

| Address | Expected Result |
|---|---|
| `/` | The configured website is delivered with the selected theme. |
| `/_editor` | The first user account can maintain content. |
| `/_admin` | The separate technical password opens the full project administration. |
| `/_templates` | The same technical password opens the section-first Template Builder (Alpha). |

Also check every language and route, the navigation, and used forms. Save a text and an image in `/_editor` as a test. If you want to use `/_templates`, additionally open a `page-*.tpl`, change nothing at first, and check whether its top-level sections are recognized without warnings.

The last installation step replaces the provided `_admin` password hash and locks `/_install`. Subsequently, remove `_install/` from production delivery; the correct order and further security checks are described in the [Deployment Manual](deployment.md#_install-after-setup).

## Next Steps

- [Concepts](concepts.md) explains architecture, data flow, and separation of concerns.
- [Developer Manual](development.md) deepens kernel, APIs, callbacks, and custom modules.
- [`/_install` Reference](_install.md) documents all options and writing processes.
- [`/_admin` Operation](_admin.md) guides through full project administration.
- [`/_templates` Operation](_templates.md) explains the section-first Template Builder in Alpha status.
- [`/_editor` Operation](_editor.md) explains the subsequent, permission-controlled content maintenance.
- [Deployment](deployment.md) guides through web server configuration, security, backups, and go-live.
