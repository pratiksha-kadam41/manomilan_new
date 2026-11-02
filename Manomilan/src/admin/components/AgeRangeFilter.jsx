import { X } from "lucide-react";

const clamp = (value) => {
  if (value === "" || value === null || value === undefined) return "";
  const numeric = Math.trunc(Number(value));
  if (!Number.isFinite(numeric)) return "";
  return String(Math.min(120, Math.max(1, numeric)));
};

export default function AgeRangeFilter({
  from = "",
  to = "",
  onChange,
  onClear,
  error = "",
  disabled = false,
}) {
  const update = (key, value) => {
    const nextFrom = key === "from" ? clamp(value) : from;
    const nextTo = key === "to" ? clamp(value) : to;
    onChange?.(nextFrom, nextTo);
  };

  const hasValue = from !== "" || to !== "";

  return (
    <div className={`ui-age ${error ? "invalid" : ""}`.trim()} role="group" aria-label="Filter by age range">
      <label className="ui-age-label">
        From age
        <input
          type="number"
          inputMode="numeric"
          min="1"
          max="120"
          value={from}
          disabled={disabled}
          placeholder="Min"
          onChange={(event) => update("from", event.target.value)}
        />
      </label>
      <span className="ui-age-sep" aria-hidden="true">–</span>
      <label className="ui-age-label">
        To age
        <input
          type="number"
          inputMode="numeric"
          min="1"
          max="120"
          value={to}
          disabled={disabled}
          placeholder="Max"
          onChange={(event) => update("to", event.target.value)}
        />
      </label>
      <button
        type="button"
        className="ui-age-clear"
        onClick={() => onClear?.()}
        disabled={disabled || !hasValue}
        aria-label="Clear age range"
        title="Clear age range"
      >
        <X size={15} />
      </button>
      {error ? <span className="ui-field-error" role="alert">{error}</span> : null}
    </div>
  );
}
