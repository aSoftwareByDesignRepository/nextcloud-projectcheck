# Cost-pricing operator migration note

**Canonical upgrade / schema repair notes:** [`AUDIT-EVIDENCE.md`](./AUDIT-EVIDENCE.md)

Cost-pricing tables shipped in 2.0.37–2.0.44. Later tracks (settlement 2.0.75, mobile 2.0.83–2.0.86)
reuse the same `ProjectCheckSchemaEnsurer` repair path. Always run `occ upgrade` as the web-server user
after updating the app.
