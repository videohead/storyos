"use client";

import { useEffect, useMemo, useState } from "react";

type ConnectionRecord = {
  id: number;
  title?: string;
  provider_type?: string;
  environment?: string;
};

type CatalogEntry = {
  id: string;
  name?: string;
  modality?: string;
  status?: string;
  enabled?: boolean;
  template_id?: number;
};

type Snapshot = {
  synced_at?: string;
  entries?: CatalogEntry[];
};

type ApiResult = {
  message?: string;
  snapshot?: Snapshot;
  prepared?: Array<{ entry_id: string; template_id: number }>;
  failed?: Array<{ entry_id: string; message: string }>;
};

type EntryAction = "enable" | "disable" | "materialize" | "download";

function badgeClass(status: string): string {
  if (status === "ready") {
    return "bg-emerald-100 text-emerald-800 border-emerald-300";
  }
  if (status === "needs_nodes" || status === "needs_models") {
    return "bg-amber-100 text-amber-800 border-amber-300";
  }
  if (status === "unmappable" || status === "withdrawn") {
    return "bg-rose-100 text-rose-800 border-rose-300";
  }
  return "bg-slate-100 text-slate-800 border-slate-300";
}

export function ComfyCatalogConsole() {
  const [connections, setConnections] = useState<ConnectionRecord[]>([]);
  const [connectionId, setConnectionId] = useState<number | null>(null);
  const [entries, setEntries] = useState<CatalogEntry[]>([]);
  const [syncedAt, setSyncedAt] = useState<string>("");
  const [busy, setBusy] = useState(false);
  const [status, setStatus] = useState("Loading connections...");
  const [log, setLog] = useState<string[]>([]);

  const summary = useMemo(() => {
    const total = entries.length;
    const mappable = entries.filter((entry) => !!entry.modality).length;
    const enabled = entries.filter((entry) => !!entry.enabled).length;
    const materialized = entries.filter((entry) => !!entry.template_id).length;

    return { total, mappable, enabled, materialized };
  }, [entries]);

  const addLog = (message: string) => {
    const timestamp = new Date().toLocaleTimeString();
    setLog((previous) => [`${timestamp} - ${message}`, ...previous].slice(0, 10));
  };

  const loadConnections = async () => {
    setBusy(true);
    setStatus("Loading Comfy connections...");

    try {
      const response = await fetch("/api/worldgraph/connections", {
        method: "GET",
        cache: "no-store",
      });

      const payload = (await response.json()) as ConnectionRecord[] | { message?: string };
      if (!response.ok) {
        throw new Error("message" in payload ? payload.message : "Failed to load connections.");
      }

      const list = Array.isArray(payload) ? payload : [];
      setConnections(list);

      if (!list.length) {
        setConnectionId(null);
        setEntries([]);
        setStatus("No ComfyUI connections found.");
        addLog("No ComfyUI connections available.");
        return;
      }

      const localFirst = list.find((item) => item.environment === "local") ?? list[0];
      setConnectionId(localFirst.id);
      setStatus("Connection list loaded.");
      addLog("Loaded available ComfyUI connections.");
    } catch (error) {
      const message = error instanceof Error ? error.message : "Failed to load connections.";
      setStatus(message);
      addLog(message);
    } finally {
      setBusy(false);
    }
  };

  const loadCatalog = async (targetConnectionId: number) => {
    setBusy(true);
    setStatus("Loading catalog snapshot...");

    try {
      const response = await fetch(`/api/worldgraph/connections/${targetConnectionId}/catalog`, {
        method: "GET",
        cache: "no-store",
      });
      const payload = (await response.json()) as { snapshot?: Snapshot; message?: string };

      if (!response.ok) {
        throw new Error("message" in payload ? payload.message : "Failed to load catalog.");
      }

      const snapshot = payload.snapshot ?? {};
      setEntries(snapshot.entries ?? []);
      setSyncedAt(snapshot.synced_at ?? "");
      setStatus("Catalog snapshot loaded.");
      addLog("Loaded catalog snapshot.");
    } catch (error) {
      const message = error instanceof Error ? error.message : "Failed to load catalog.";
      setStatus(message);
      addLog(message);
    } finally {
      setBusy(false);
    }
  };

  const runConnectionAction = async (
    action: "sync" | "prepare",
    inProgressLabel: string
  ) => {
    if (!connectionId) {
      return;
    }

    setBusy(true);
    setStatus(inProgressLabel);

    try {
      const response = await fetch(
        `/api/worldgraph/connections/${connectionId}/catalog/${action}`,
        {
          method: "POST",
          body: "{}",
        }
      );
      const payload = (await response.json()) as ApiResult & { message?: string };

      if (!response.ok) {
        throw new Error(payload.message ?? `Failed to ${action} catalog.`);
      }

      if (payload.snapshot) {
        setEntries(payload.snapshot.entries ?? []);
        setSyncedAt(payload.snapshot.synced_at ?? "");
      }

      const message = payload.message ?? `Catalog ${action} complete.`;
      setStatus(message);
      addLog(message);

      if (action === "prepare") {
        const preparedCount = payload.prepared?.length ?? 0;
        const failedCount = payload.failed?.length ?? 0;
        addLog(`Prepared ${preparedCount} mappable template(s), ${failedCount} failed.`);
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : `Failed to ${action} catalog.`;
      setStatus(message);
      addLog(message);
    } finally {
      setBusy(false);
    }
  };

  const runEntryAction = async (entryId: string, action: EntryAction) => {
    if (!connectionId) {
      return;
    }

    setBusy(true);
    setStatus(`Running ${action} for ${entryId}...`);

    try {
      const response = await fetch(
        `/api/worldgraph/connections/${connectionId}/catalog/entries`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entryId, action }),
        }
      );
      const payload = (await response.json()) as ApiResult & { message?: string };

      if (!response.ok) {
        throw new Error(payload.message ?? `Failed to ${action} entry ${entryId}.`);
      }

      if (payload.snapshot) {
        setEntries(payload.snapshot.entries ?? []);
        setSyncedAt(payload.snapshot.synced_at ?? "");
      }

      const message = payload.message ?? `Entry ${action} completed.`;
      setStatus(message);
      addLog(message);
    } catch (error) {
      const message = error instanceof Error ? error.message : `Failed to ${action} entry ${entryId}.`;
      setStatus(message);
      addLog(message);
    } finally {
      setBusy(false);
    }
  };

  useEffect(() => {
    void loadConnections();
  }, []);

  useEffect(() => {
    if (connectionId) {
      void loadCatalog(connectionId);
    }
  }, [connectionId]);

  return (
    <section className="space-y-6">
      <header className="space-y-2">
        <h1 className="text-3xl font-semibold text-wg-espresso">Comfy Connection Catalog</h1>
        <p className="text-sm text-wg-charcoal/80">
          One workflow for headless and wp-admin: sync catalog, auto-prepare mappable templates,
          then download requirements when available.
        </p>
      </header>

      <div className="rounded-wg border border-wg-sepia/50 bg-white/70 p-4 shadow-wg">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <label className="flex flex-col gap-1 text-sm font-medium text-wg-espresso">
            Active ComfyUI Connection
            <select
              className="rounded-wg border border-wg-sepia/60 bg-white px-3 py-2 text-sm"
              value={connectionId ?? ""}
              onChange={(event) => setConnectionId(Number(event.target.value))}
              disabled={busy || !connections.length}
            >
              {connections.map((connection) => (
                <option key={connection.id} value={connection.id}>
                  #{connection.id} {connection.title ?? "Connection"} ({connection.environment ?? "unknown"})
                </option>
              ))}
            </select>
          </label>

          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              className="rounded-wg border-2 border-wg-espresso bg-wg-espresso px-4 py-2 font-headline text-xs font-bold uppercase tracking-wider text-wg-ivory disabled:cursor-not-allowed disabled:opacity-60"
              onClick={() => void runConnectionAction("sync", "Syncing provider catalog...")}
              disabled={busy || !connectionId}
            >
              Sync Catalog
            </button>
            <button
              type="button"
              className="rounded-wg border-2 border-wg-sepia bg-wg-sepia px-4 py-2 font-headline text-xs font-bold uppercase tracking-wider text-wg-ink disabled:cursor-not-allowed disabled:opacity-60"
              onClick={() =>
                void runConnectionAction(
                  "prepare",
                  "Preparing mappable templates (sync + enable + materialize)..."
                )
              }
              disabled={busy || !connectionId}
            >
              Auto-Prepare Mappable Templates
            </button>
          </div>
        </div>

        <div className="mt-4 space-y-2 text-sm">
          <p className="rounded-wg border border-wg-blueprint/25 bg-wg-blueprint/5 px-3 py-2 text-wg-charcoal">
            Status: {status}
          </p>
          <p className="text-wg-charcoal/80">
            Last synced: {syncedAt || "Not synced yet"}
          </p>
          <p className="text-wg-charcoal/80">
            Templates: {summary.total} total, {summary.mappable} mappable, {summary.enabled} enabled, {summary.materialized} materialized.
          </p>
          {log.length > 0 ? (
            <ul className="list-disc space-y-1 pl-5 text-xs text-wg-charcoal/80">
              {log.map((line) => (
                <li key={line}>{line}</li>
              ))}
            </ul>
          ) : null}
        </div>
      </div>

      <div className="space-y-3">
        {entries.length === 0 ? (
          <p className="rounded-wg border border-wg-sepia/40 bg-white/50 px-4 py-3 text-sm text-wg-charcoal/80">
            No templates discovered yet. Run Sync Catalog to fetch provider templates.
          </p>
        ) : (
          entries.map((entry) => {
            const entryName = entry.name || entry.id;
            const entryStatus = entry.status || "unknown";
            const isUnmappable = !entry.modality;

            return (
              <article
                key={entry.id}
                className="rounded-wg border border-wg-sepia/40 bg-white/80 p-4 shadow-wg"
              >
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                  <div>
                    <h2 className="text-lg font-semibold text-wg-espresso">{entryName}</h2>
                    <p className="text-xs text-wg-charcoal/75">ID: {entry.id}</p>
                    <div className="mt-2 flex flex-wrap gap-2 text-xs">
                      <span className={`rounded-full border px-2 py-1 ${badgeClass(entryStatus)}`}>
                        {entryStatus}
                      </span>
                      <span className="rounded-full border border-wg-sepia/40 px-2 py-1 text-wg-charcoal/80">
                        {entry.modality ?? "unmappable"}
                      </span>
                      <span className="rounded-full border border-wg-sepia/40 px-2 py-1 text-wg-charcoal/80">
                        {entry.enabled ? "enabled" : "not enabled"}
                      </span>
                      <span className="rounded-full border border-wg-sepia/40 px-2 py-1 text-wg-charcoal/80">
                        {entry.template_id ? `template #${entry.template_id}` : "not materialized"}
                      </span>
                    </div>
                  </div>

                  <div className="flex flex-wrap gap-2">
                    <button
                      type="button"
                      className="rounded-wg border border-wg-espresso px-3 py-2 text-xs font-semibold text-wg-espresso disabled:cursor-not-allowed disabled:opacity-60"
                      disabled={busy}
                      onClick={() =>
                        void runEntryAction(entry.id, entry.enabled ? "disable" : "enable")
                      }
                    >
                      {entry.enabled ? "Disable" : "Enable"}
                    </button>
                    <button
                      type="button"
                      className="rounded-wg border border-wg-espresso px-3 py-2 text-xs font-semibold text-wg-espresso disabled:cursor-not-allowed disabled:opacity-60"
                      disabled={busy || isUnmappable}
                      onClick={() => void runEntryAction(entry.id, "materialize")}
                    >
                      Materialize
                    </button>
                    <button
                      type="button"
                      className="rounded-wg border border-wg-sepia px-3 py-2 text-xs font-semibold text-wg-charcoal disabled:cursor-not-allowed disabled:opacity-60"
                      disabled={busy}
                      onClick={() => void runEntryAction(entry.id, "download")}
                    >
                      Download Requirements
                    </button>
                  </div>
                </div>
              </article>
            );
          })
        )}
      </div>
    </section>
  );
}
