# World Graph Studio

> Your ideas. Your assets. No credits needed.

World Graph Studio is a free, open-source, self-hosted creative production
platform for worldbuilding, storytelling, story analysis, asset generation,
and production planning. It runs in WordPress and keeps the people, places,
scenes, shots, media, and production decisions for a project connected in one
Story Graph.

Use World Graph Studio as a private creative workspace, publish from it when
you choose, and connect the local or hosted AI tools that fit your workflow.
The platform does not sell generation credits or require a single model
provider.

## What ships today

- A structured Story Graph with 15 WordPress content types, nine taxonomies,
  reusable relationships, and Structured Content Fields.
- Project, world, character, location, prop, organization, episode, scene,
  shot, sound, storyboard, asset, editorial, template, and connection tools.
- An AI Editor, Story Graph-aware analysis, continuity checks, relationship
  analytics, semantic-search fallbacks, and 50+ specialist creative advisors.
- Template-backed generation jobs, provider connections, WordPress media
  imports, provenance, status tracking, cancellation, and scheduled batches.
- World Graph Studio JSON import plus Markdown screenplay and storyboard
  export.
- Optional outbound Celtx synchronization plus EDL parsing, preview, and
  formatting tools.
- A permission-aware REST API and WordPress Abilities for tools, resources,
  and prompts.

Broader file-based script interchange—such as FDX, Fade In, Highland, Story
Architect, and additional professional script exports—is on hold. The JSON,
Markdown, Celtx, and EDL-helper surfaces already in the product remain
available. See [Delivery status](about/Delivery_Status.md) for the exact
boundary. The bundled Web Stories source is an extension prototype, not a
current release feature.

## Why it exists

Creative work is more than a prompt and an output file. World Graph Studio
keeps narrative context and production context together, so a character can
stay connected to a world, a scene, a shot, a generated asset, an editorial
decision, and the reasoning behind those choices.

With self-hosted and open models, creators can work without a platform-level
credit meter, proprietary project format, or mandatory cloud account. Hosted
providers remain optional and may apply their own prices, quotas, licenses,
and usage policies.

## Architecture

```text
Creator
  |
  v
WordPress + World Graph Studio
  |-- Story Graph, SCF, media, REST, and admin workflows
  |-- AI Editor, creative advisors, search, and continuity
  |-- connections, templates, generation jobs, and provenance
  |
  +--> optional LLM connection
  +--> optional ComfyUI / Comfy Cloud / provider connection
  +--> optional Celtx sync and EDL format tooling
```

WordPress is the application and source of truth. External AI and generation
services are replaceable connections; they do not own the Story Graph.

## Quick start

### Requirements

- Docker Desktop or Docker Engine
- [Lando](https://docs.lando.dev/getting-started/installation.html)
- Git
- The [Frost block theme](https://github.com/wpengine/frost) installed as the
  parent theme for the included World Graph child theme
- An API-connected LLM only if you want AI Editor or advisor features
- ComfyUI, Comfy Cloud, or another configured provider only if you want
  automated asset generation

### Start the local site

```bash
git clone <repository-url> worldgraph
cd worldgraph
lando start
lando info
```

Before activating the included `worldgraph-child` theme, download Frost from
the [official Frost repository](https://github.com/wpengine/frost)
and install it as `wordpress/wp-content/themes/frost`. Frost is the parent theme
declared by the child theme; WordPress must be able to find it before the child
can be activated.

Lando starts WordPress, PHP 8.2, MariaDB, and phpMyAdmin. The default local URL
is `https://worldgraph.lndo.site`.

For a fresh WordPress database, complete WordPress installation and activate
the required plugins:

```bash
lando wp core install \
  --url=https://worldgraph.lndo.site \
  --title="World Graph Studio" \
  --admin_user=admin \
  --admin_password=<choose-a-password> \
  --admin_email=<your-email>
lando wp plugin activate secure-custom-fields worldgraph
```

If you are restoring an existing database instead, import a serialization-safe
WordPress backup before activating `worldgraph`. Activation migrates supported
legacy StoryOS identifiers to the `worldgraph` namespace.

The Lando app name also changed to `worldgraph`. Lando uses that name when it
identifies services and database volumes, so an existing database from the old
app name is not moved automatically. Export it before switching Landofiles,
then import the archive into the new app and activate `worldgraph`.

Open **World Graph Studio > Setup** to configure an LLM and any optional
generation connections. Core story and production planning work without those
services.

## Documentation

Start with the [documentation guide](about/README.md), then use these primary
references:

- [Product overview](about/marketing/overview.md)
- [Delivery status](about/Delivery_Status.md)
- [Product requirements](about/World_Graph_Studio_PRD.md)
- [Architecture](about/World_Graph_Studio_Architecture.md)
- [User guide](about/example-workflow/USER_GUIDE.md)
- [Deployment and connections](about/Deployment_and_Connections.md)
- [Story Graph specification](about/Story_Graph_Specification.md)
- [REST API](about/REST_API_Specification.md)

## Namespace

The product name is **World Graph Studio**. Machine-readable identifiers use
`worldgraph`, PHP symbols use `WorldGraph`, and constants and environment
variables use `WORLDGRAPH_`.

## Development

Run the PHP test suite without writing PHPUnit's result cache:

```bash
./vendor/bin/phpunit \
  -c wordpress/wp-content/plugins/worldgraph/tests/phpunit.xml \
  --testsuite "World Graph Studio" \
  --do-not-cache-result
```

Development conventions and runtime-specific commands are in
[`.github/instructions/instructions.md`](.github/instructions/instructions.md).
Contributions are welcome; see the
[contributing guide](about/CONTRIBUTING_World_Graph_Studio.md).

## License

The repository is licensed under the [MIT License](LICENSE). WordPress plugin
headers in this repository declare GPL v2-or-later for the distributed plugin
components. No change to either license statement is implied by the rebrand.

---

Build worlds. Connect ideas. Generate anything. No credits needed.
