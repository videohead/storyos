"""Dynamic discovery and loading of provider type implementations."""

from __future__ import annotations

import importlib
import inspect
import pkgutil
from types import ModuleType
from typing import Optional

from providers.base import ProviderInterface
from providers.registry import ProviderRegistry


def _provider_classes(module: ModuleType) -> list[type[ProviderInterface]]:
    """Return concrete provider classes defined by a module."""
    discovered: list[type[ProviderInterface]] = []

    for _, provider_cls in inspect.getmembers(module, inspect.isclass):
        if (
            provider_cls is not ProviderInterface
            and issubclass(provider_cls, ProviderInterface)
            and provider_cls.__module__ == module.__name__
            and not inspect.isabstract(provider_cls)
        ):
            discovered.append(provider_cls)

    return discovered


def load_providers(
    registry: Optional[ProviderRegistry] = None,
    package_name: str = "providers",
) -> ProviderRegistry:
    """Discover and register provider implementations in a package.

    Provider modules are ordinary Python modules beneath ``package_name``.
    A module can expose one or more concrete ``ProviderTypeInterface``
    subclasses. Modules that fail to import are allowed to raise so startup
    reports a broken provider deployment instead of running with a partial
    registry.
    """
    registry = registry or ProviderRegistry()
    package = importlib.import_module(package_name)

    if not hasattr(package, "__path__"):
        raise ValueError(f"provider package is not a package: {package_name}")

    module_names = sorted(
        module_info.name
        for module_info in pkgutil.iter_modules(package.__path__)
        if not module_info.name.startswith("_")
        and module_info.name
        not in {"base", "capability_loader", "discovery", "loader", "registry"}
    )

    for module_name in module_names:
        module = importlib.import_module(f"{package_name}.{module_name}")
        for provider_cls in _provider_classes(module):
            registry.register(provider_cls)

    return registry
