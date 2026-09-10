# Getting Started with Nino

**Language:** English · [Deutsch](getting-started.de.md)

**Last updated:** September 7, 2026 · **Nino version:** 1.0.0-beta

This guide leads you on the shortest path from a fresh checkout to a locally running Nino website. If you instead want to look up every field and writing process of the wizard, read the [Setup Wizard](setup.md) reference; technical backgrounds are explained in the [Concepts](concepts.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Recipes](recipes/README.md) · [Getting Started](getting-started.md) · [Setup Wizard](setup.md) · [`/_admin` Workbench](_admin.md) · [Features](features.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Important:** A fresh checkout contains the kernel, the workbench, the modules, the features and the installation library, but not yet a complete project state. The setup wizard - what `/_admin` shows until it is done - creates and fills the required project directories; only then does the website run.

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
| [3. Routes](setup.md#3-routes) | Which first pages, public paths, and metadata are created? |
| [4. Personal Information](setup.md#4-personal-information) | Which central company and website values are available as textfills? |
| [5. Accounts](setup.md#5-accounts) | Which developer account(s) sign in to the workbench with full access? |
| [6. Finish](setup.md#6-finish) | Which recovery password opens `/_admin/recovery.php` when the accounts are broken - and locks the wizard? |

The wizard automatically resolves dependencies between selected modules and the page templates used. The look is not among its questions: the base unit delivers one theme, `assets/theme.css`, and the two frame templates it is drawn against - see [The Look](setup.md#the-look).

The accounts of step 5 are developers with full rights. Editor accounts with content permissions only are created later in the workbench's Users panel.

## Verify the Result

After completion, open:

| Address | Expected Result |
|---|---|
| `/` | The configured website is delivered, in the theme the base unit brought. |
| `/_admin` | The root account opens the workbench with every panel: content, structure and system. |
| `/_admin#templates` | The Templates panel, the section-first Template Builder (Alpha). |

Also check every language and route, the navigation, and used forms. Save a text and an image as a test. In the Templates panel, open a `page-*.tpl`, change nothing at first, and check whether its top-level sections are recognized without warnings.

Newsletter and Search are features, not wizard modules, and a checkout ships none: when the project needs one, copy its directory from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features) into `features/`, switch it on in the workbench's **Features** panel (System group), then reload the workbench for its panel to appear. See [Features](features.md).

The last step sets the recovery password and locks the wizard. Subsequently, remove `_admin/install/` from production delivery; everything it copied stays where it wrote it. The correct order and further security checks are described in the [Deployment Manual](deployment.md#the-wizard-after-setup).

## Next Steps

- [Concepts](concepts.md) explains architecture, data flow, and separation of concerns.
- [Developer Manual](development.md) deepens kernel, APIs, callbacks, and custom modules.
- [Setup Wizard](setup.md) documents all options and writing processes.
- [`/_admin` Workbench](_admin.md) guides through every panel, the accounts and the recovery page.
- The **Template Builder** - page templates composed from whole sections - is a feature from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features); its [manual](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.md) is there too.
- [Features](features.md) explains how an installable feature is switched on, configured and updated.
- [Deployment](deployment.md) guides through web server configuration, security, backups, and go-live.
