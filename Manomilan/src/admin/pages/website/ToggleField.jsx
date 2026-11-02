export default function ToggleField({ label, description, checked, onChange, disabled = false }) {
  return (
    <label className={`wm-toggle ${checked ? "on" : ""} ${disabled ? "disabled" : ""}`}>
      <span className="wm-toggle-copy">
        <strong>{label}</strong>
        {description ? <small>{description}</small> : null}
      </span>
      <span className="wm-switch">
        <input type="checkbox" checked={checked} onChange={onChange} disabled={disabled} />
        <span className="wm-switch-track" aria-hidden="true">
          <span className="wm-switch-thumb" />
        </span>
      </span>
    </label>
  );
}
