import { FileText, Trash2 } from "lucide-react";

export function TicketFileInput({ label, file, currentAttachment, onChange, disabled = false }) {
  return (
    <label className="tk-field tk-file-field">
      <span>{label}</span>
      <span className="tk-file-control">
        <span>Choose File</span>
        <strong>{file?.name || "No file chosen"}</strong>
        <input type="file" onChange={onChange} disabled={disabled} />
      </span>
      {currentAttachment?.url ? (
        <a
          className="tk-current-attachment"
          href={currentAttachment.url}
          target="_blank"
          rel="noreferrer"
        >
          <FileText size={14} />
          {currentAttachment.name || "Current Attachment"}
        </a>
      ) : null}
    </label>
  );
}

export function TicketItemForm({ item, index, options, onChange, onRemove, canRemove, disabled = false }) {
  return (
    <fieldset className="tk-item-card">
      <div className="tk-item-topline">
        <label className="tk-field tk-item-title">
          <span>Title <em>*</em></span>
          <input
            value={item.title}
            maxLength={255}
            onChange={(event) => onChange(index, "title", event.target.value)}
            disabled={disabled}
          />
        </label>
        <label className="tk-field tk-item-select">
          <span>Priority</span>
          <select value={item.priority} onChange={(event) => onChange(index, "priority", event.target.value)} disabled={disabled}>
            {options.priorities.map((value) => <option key={value}>{value}</option>)}
          </select>
        </label>
        <label className="tk-field tk-item-select">
          <span>Severity</span>
          <select value={item.severity} onChange={(event) => onChange(index, "severity", event.target.value)} disabled={disabled}>
            {options.severities.map((value) => <option key={value}>{value}</option>)}
          </select>
        </label>
        <label className="tk-field tk-item-select">
          <span>Item Type</span>
          <select value={item.item_type} onChange={(event) => onChange(index, "item_type", event.target.value)} disabled={disabled}>
            {options.item_types.map((value) => <option key={value}>{value}</option>)}
          </select>
        </label>
        <button
          type="button"
          className="tk-remove-item"
          disabled={!canRemove}
          onClick={() => onRemove(index)}
          aria-label="Remove item"
        >
          <Trash2 size={16} />
        </button>
      </div>
      <div className="tk-item-bottomline">
        <label className="tk-field tk-item-description">
          <span>Description <em>*</em></span>
          <textarea
            rows="3"
            value={item.description}
            onChange={(event) => onChange(index, "description", event.target.value)}
            disabled={disabled}
          />
        </label>
        <TicketFileInput
          label="Attachment (Optional)"
          file={item.attachment}
          currentAttachment={item.attachment_url
            ? { url: item.attachment_url, name: "Current Attachment" }
            : null}
          onChange={(event) => onChange(index, "attachment", event.target.files?.[0] || null)}
          disabled={disabled}
        />
      </div>
    </fieldset>
  );
}
