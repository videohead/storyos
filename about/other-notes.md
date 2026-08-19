he most useful thing I found: the concept you're describing already exists in the codebase by name, and was never implemented. includes/cpts/connection.php documents enabled_structures as "JSON array of generation structures enabled for this connection, e.g. ["character-sheet","scene-image"]". The REST spec, the plugin's own ARCHITECTURE.md and SETUP_GUIDE.md all show "workflow": "character-sheet". README lists "Templates: base, character-sheet, environment, storyboard". And storyos_template has a generation_structure field.

But nothing defines what a "structure" is. Comfy_Bootstrap writes generation_structure = output_type(...), collapsing it to image|video — a duplicate of the modality's output type carrying no authorial meaning. So I named the layer Generation Intent and made those two existing fields mean what the docs always claimed.

Three layers, and authors only ever touch the first:

Layer	Answers	Example
Intent	What am I making?	"Character portrait"
Modality	What shape is the job?	text_to_image
Template	Which graph runs it?	flux_txt2img_basic
On your "simple at first, complicated as needed" framing — I put that in a resolution cascade rather than a config requirement: per-post override → per-CPT preference → global default → automatic (first active Template matching the intent's modality). That last layer is the important one. It means generation works with zero configuration the moment Comfy_Bootstrap provisions the default template. Generate Preferences only ever overrides; it's never a gate.

The metabox becomes intent chips — [ Portrait ] [ Full body ] [ Turnaround ] — with no Template control at all at the default disclosure level. Your point about users fleeing to limited tools is why generic-image / generic-video intents are always present on every CPT: that is the simple text-to-image tool, sitting inside the same surface.

Two concrete bugs the current metabox has that this fixes: the button is hardcoded "Generate image" even when the selected Template is video, and runnable_templates() silently drops templates whose required media slots don't resolve — an author sees options vanish with no explanation. The spec renders those disabled with the reason inline ("Needs a featured image").

One thing I'd flag as a judgment call worth your review: I proposed build_prompt() be superseded by per-intent prompt recipes. Today it mines one fixed meta-key list and appends one cinematic suffix for every CPT and purpose — a turnaround and an establishing shot get identically-shaped prompts. Per-intent recipes are a bigger change than the rest of steps 1–3 and touch output quality directly, so it may be worth splitting.

Steps 1–3 are shippable alone: automatic resolution plus a correct button label improves the existing metabox with no new configuration surface. Step 4 is where authors actually feel it.