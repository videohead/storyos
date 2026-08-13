from providers.base import ProviderTypeInterface
from providers.loader import load_providers
from providers.registry import ProviderRegistry


def test_load_providers_discovers_bundled_provider_modules():
    registry = load_providers()

    assert sorted(registry._providers) == ["comfyui", "nova_reel", "veo"]
    assert all(
        issubclass(provider_cls, ProviderTypeInterface)
        for provider_cls in registry._providers.values()
    )


def test_load_providers_registers_into_existing_registry():
    registry = ProviderRegistry()

    loaded = load_providers(registry=registry)

    assert loaded is registry
    assert registry.has("comfyui")
    assert registry.has("nova_reel")
    assert registry.has("veo")


def test_registered_providers_declare_generate_and_download_operations():
    registry = load_providers()

    for provider_type in ("comfyui", "nova_reel", "veo"):
        assert registry.supports_operation(provider_type, "generate")
        assert registry.supports_operation(provider_type, "download_artifacts")
