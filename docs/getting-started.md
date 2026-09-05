# Getting Started with Nino

**Language:** English · [Deutsch](getting-started.de.md)

**Last updated:** September 5, 2026 · **Nino version:** 0.13.0-beta

This guide leads you on the shortest path from a fresh checkout to a locally running Nino website. If you instead want to look up every field and writing process of the wizard, read the [Setup Wizard](setup.md) reference; technical backgrounds are explained in the [Concepts](concepts.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [Setup Wizard](setup.md) · [`/_admin` Workbench](_admin.md) · [Templates Panel](templates.md) · [Design Panel](appearance.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Important:** A fresh checkout contains the kernel, the workbench, the modules and the installation library, but not yet a complete project state. The setup wizard - what `/_admin` shows until it is done - creates and fills the required project directories; only then does the website run.

## Prerequisites

For local setup, PHP 8.4 or newer, the PHP extensions checked by Nino, and write permissions in the project root are required. Git is necessary if the project is checked out directly from the repository.

The first installation step checks the version, extensions, and write permissions. Missing directories such as `templates/`, `text/`, `elements/`, or `images/` are expected at this point—PHP must only be able to create them.

> **Security:** Perform the setup locally or in another protected environment. Until completion, the wizard has no access protection, and no account exists yet.

## Check out the project and start

```bash
git clone https://github.com/dapeio/nino.git my-website
cd my-website
php -S 127.0.0.1:8000 router.php
```

Then open <http://127.0.0.1:8000/_admin>. `router.php` maps the local routing; for production, a dedicated web server configuration is required.

## The Ten Steps

As long as the wizard is not completed, you can return to earlier steps and reapply settings. What is replaced, added, or preserved in the process is described in the [Setup Wizard](setup.md#navigation-and-saving) reference.

| Step | Decision |
|---|---|
| [1. Environment](setup.md#1-environment) | Are PHP, extensions, and write permissions ready for use? |
| [2. Setup](setup.md#2-setup) | Which languages and functional modules does the project require? |
| [3. Themes](setup.md#3-themes) | Which visual starting point should be copied? |
| [4. Header](setup.md#4-header) | Which independently previewed header frame should be installed? |
| [5. Footer](setup.md#5-footer) | Which independently previewed footer frame should be installed? |
| [6. Design](setup.md#6-design) | Which colours, type scale, spacing, and shaping should the theme use? |
| [7. Routes](setup.md#7-routes) | Which first pages, public paths, and metadata are created? |
| [8. Personal Information](setup.md#8-personal-information) | Which central company and website values are available as textfills? |
| [9. Accounts](setup.md#9-accounts) | Which developer account(s) sign in to the workbench with full access? |
| [10. Finish](setup.md#10-finish) | Which recovery password opens `/_admin/recovery.php` when the accounts are broken - and locks the wizard? |

The wizard automatically resolves dependencies between selected modules and the page templates used. Theme, Header, Footer, and Design are separate consecutive decisions; after completion, the Design panel is where they change.

The accounts of step 9 are developers with full rights. Editor accounts with content permissions only are created later in the workbench's Users panel.

## Verify the Result

After completion, open:

| Address | Expected Result |
|---|---|
| `/` | The configured website is delivered with the selected theme. |
| `/_admin` | The root account opens the workbench with every panel: content, structure and system. |
| `/_admin#design` | The Design panel with Theme, Header, Footer and Design. |
| `/_admin#templates` | The Templates panel, the section-first Template Builder (Alpha). |

Also check every language and route, the navigation, and used forms. Save a text and an image as a test. In the Templates panel, open a `page-*.tpl`, change nothing at first, and check whether its top-level sections are recognized without warnings.

The last step sets the recovery password and locks the wizard. Subsequently, remove `_admin/install/` from production delivery; this also retires the catalogue-backed tabs of the Design panel, while its Design tab keeps working. The correct order and further security checks are described in the [Deployment Manual](deployment.md#the-wizard-after-setup).

## Next Steps

- [Concepts](concepts.md) explains architecture, data flow, and separation of concerns.
- [Developer Manual](development.md) deepens kernel, APIs, callbacks, and custom modules.
- [Setup Wizard](setup.md) documents all options and writing processes.
- [`/_admin` Workbench](_admin.md) guides through every panel, the accounts and the recovery page.
- [Templates Panel](templates.md) explains the section-first Template Builder in Alpha status.
- [Design Panel](appearance.md) explains the four appearance editors.
- [Deployment](deployment.md) guides through web server configuration, security, backups, and go-live.
