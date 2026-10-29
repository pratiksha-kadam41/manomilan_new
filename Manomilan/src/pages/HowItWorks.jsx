// style "red" = crimson card with white icon circle, "white" = white card with crimson icon circle
const STEPS = [
  { no: "01", title: "Registration", icon: "fa-user-plus", style: "red" },
  { no: "02", title: "Create Profile", icon: "fa-id-card", style: "white" },
  { no: "03", title: "Verification", icon: "fa-shield-alt", style: "red" },
  { no: "04", title: "Send Interest", icon: "fa-heart-circle-plus", style: "white" },
  { no: "05", title: "Start Meetup", icon: "fa-handshake", style: "red" },
  { no: "06", title: "Congratulations!", icon: "fa-hands-clapping", style: "white" },
];

export default function HowItWorks() {
  return (
    <main>
      <section id="how-it-works" className="how">
        <div className="site-container">
          <h2 className="sec-title">How It Works</h2>
          <p className="sec-sub">Finding your partner in six simple, secure steps.</p>

          <div className="steps-grid">
            {STEPS.map((s) => (
              <div key={s.no} className={`step-card ${s.style} lift`}>
                <div className="step-icon">
                  <i className={`fas ${s.icon}`} />
                </div>
                <div className="step-no">{s.no}</div>
                <h3>{s.title}</h3>
              </div>
            ))}
          </div>
        </div>
      </section>
    </main>
  );
}