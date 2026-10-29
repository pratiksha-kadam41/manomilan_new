export default function ComingSoon({ path }) {
  const title = path.replace("/", "").replace(/[-_]/g, " ") || "page";
  return (
    <main className="about-top">
      <section className="gray-sec" style={{ textAlign: "center", minHeight: 300 }}>
        <div className="site-container">
          <h2 className="page-title" style={{ textTransform: "capitalize" }}>{title}</h2>
          <p style={{ fontSize: "1.125rem", color: "#4b5563" }}>This page is coming soon.</p>
        </div>
      </section>
    </main>
  );
}