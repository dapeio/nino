# Extension recipes

**Additional Links:**
[Agent guide](../../AGENTS.md) · [All recipes](README.md) · [Developer Manual](../development.md) · [Concepts](../concepts.md) · [`/_admin` Workbench](../_admin.md) · [Setup Wizard](../setup.md) · [Templates Panel](../templates.md)

Step-by-step recipes for the six ways Nino is extended. They are the
practical half of the [agent guide](../../AGENTS.md): that file states the rules
every change has to keep, a recipe walks one kind of change from the first file
to its test. Section 3 of the guide, "Choose the correct extension type",
decides which recipe applies.

Like the guide, the recipes exist in English only - one canonical text for
humans and agents alike, so nobody receives two diverging instructions.

| Recipe | Use it for |
| --- | --- |
| [Add a panel to the workbench](admin-panel.md) | a new screen in `/_admin`, as a module panel or a workbench panel |
| [Add a runtime module](runtime-module.md) | PHP behaviour at boot, a shortcode, a state-changing endpoint, module configuration |
| [Add an installer module package](installer-package.md) | a feature the setup wizard can select, copy and configure |
| [Add a Section Library preset](section-preset.md) | an insertable building block for the Templates panel |
| [Write templates and installable page units](templates-and-pages.md) | page and reusable `.tpl` templates, header/footer slots, installable page units |
| [Define Element types for repeated content](element-types.md) | repeated structured content: model fields, uris, rendering, search |
