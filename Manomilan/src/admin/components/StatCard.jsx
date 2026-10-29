export default function StatCard({ value, label, color, percent }) {
  return (
    <div className="adm-card">
      <div className="adm-card-top">
        <div className="adm-icon" style={{ background: color }} />
        <div>
          <div className="adm-number">{value}</div>
          <div className="adm-label">{label}</div>
        </div>
      </div>
      {percent !== undefined && (
        <div className="adm-progress-wrap">
          <div className="adm-progress">
            <div style={{ width: `${percent}%`, background: color }} />
          </div>
          <small>{percent}% {label.split(" ")[0]}</small>
        </div>
      )}
    </div>
  );
}