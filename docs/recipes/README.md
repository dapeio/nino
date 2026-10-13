# Extension recipes

Step-by-step recipes for the five ways Nino is extended. They are the
practical half of the [agent guide](../../AGENTS.md): that file states the rules
every change has to keep, a recipe walks one kind of change from the first file
to its test. Section 3 of the guide, "Choose the correct extension type",
decides which recipe applies.

| Recipe | Use it for |
| --- | --- |
| [Add a panel to the workbench](admin-panel.md) | a new screen in `/_admin`, as a module panel or a workbench panel |
| [Add a runtime module](runtime-module.md) | PHP behaviour at boot, a shortcode, a state-changing endpoint, module configuration |
| [Add an installer module package](installer-package.md) | a module the setup wizard can select, copy and configure |
| [Define Element types for repeated content](element-types.md) | repeated structured content: model fields, uris, rendering |
| [Add a component](component.md) | a shortcode with a schema - a component, or a stack that loops the elements of a type - registered from a project or declared in a feature's manifest |

A feature - a module delivered as one directory under `features/` with a manifest,
settings, a version and its own test - is not a recipe but a manual of its own:
[Developing a feature](../features.md).
