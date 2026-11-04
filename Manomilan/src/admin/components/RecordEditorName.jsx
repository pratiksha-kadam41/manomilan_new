import "./record-editor-name.css";

export default function RecordEditorName({ record }) {
  const name = String(record?.updated_by_user_name || "").trim();
  if (!name) return null;

  return (
    <small className="record-editor-name">
      Updated by: <strong>{name}</strong>
    </small>
  );
}
