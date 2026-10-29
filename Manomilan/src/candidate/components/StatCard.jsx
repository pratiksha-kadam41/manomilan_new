export default function StatCard({ icon: Icon, value, label, from, to }) {
  return (
    <div className="cnd-card">
      <div
        className="cnd-icon"
        style={{ background: `linear-gradient(135deg, ${from}, ${to})`, boxShadow: `0 10px 20px ${from}55` }}
      >
        <Icon size={24} color="#fff" />
      </div>
      <div>
        <div className="cnd-number">{value.toLocaleString()}</div>
        <div className="cnd-label">{label}</div>
      </div>
    </div>
  );
}