const formatValue = (value) => {
  if (value === null || value === undefined || value === "") return "—";
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric.toLocaleString("en-IN") : String(value);
};

export default function AdminStats({ items = [], loading = false, label = "Statistics" }) {
  return (
    <section className="ui-stats" aria-label={label} aria-busy={loading}>
      {items.map((item) => {
        const Icon = item.icon;
        return (
          <article
            className={`ui-stat ${item.tone || "teal"} ${loading ? "is-loading" : ""}`.trim()}
            key={item.key || item.label}
          >
            <div className="ui-stat-body">
              <span className="ui-stat-label">{item.label}</span>
              <strong className="ui-stat-value">{loading ? "" : formatValue(item.value)}</strong>
              {item.hint ? <span className="ui-stat-hint">{item.hint}</span> : null}
            </div>
            {Icon ? (
              <span className="ui-stat-icon" aria-hidden="true">
                <Icon size={20} strokeWidth={2.1} />
              </span>
            ) : null}
          </article>
        );
      })}
    </section>
  );
}
