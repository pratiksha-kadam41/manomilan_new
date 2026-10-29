// Edit the details here, the page below updates by itself.
const OFFICE = {
  name: "PUNE HEAD OFFICE",
  sub: "Aaryans World School - Corporate Office",
  address: [
    "'Aachal Prime' Building, Above Hotel 'Shree Kateel',",
    "Next to Navale Bridge, New Pune-Satara Bypass Highway,",
    "Near Amrit Bloomfield, Narhe, Pune - 411041.",
  ],
  timing: "10:00 AM to 5:30 PM",
  phone: "7030932526",
  email: "aaryansmanomilan@gmail.com",
};

const BRANCHES = [
  { name: "Solapur Branch", place: "Jule Solapur" },
  { name: "Huballi Branch", place: "Karnataka" },
  { name: "Belgaum Branch", place: "Karnataka" },
];

export default function ContactUs() {
  return (
    <main>
      <section className="cu-section">
        <div className="site-container">
          <div className="cu-head">
            <h2>Contact Us</h2>
            <p>We are here to help you. Reach out to us at any of our locations.</p>
          </div>

          {/* Head office */}
          <div className="cu-office-wrap">
            <div className="cu-office">
              <div className="cu-office-pad">
                <div className="cu-office-head">
                  <h3>{OFFICE.name}</h3>
                  <p>{OFFICE.sub}</p>
                </div>

                <div className="cu-office-body">
                  <div className="cu-address">
                    <i className="fas fa-map-marker-alt" />
                    <p>
                      {OFFICE.address.map((line, k) => (
                        <span key={k}>
                          {line}
                          {k < OFFICE.address.length - 1 && <br />}
                        </span>
                      ))}
                    </p>
                  </div>

                  <div className="cu-info">
                    <div className="cu-row">
                      <div className="cu-dot"><i className="far fa-clock" /></div>
                      <div>
                        <p className="cu-label">Office Timing</p>
                        <p className="cu-value">{OFFICE.timing}</p>
                      </div>
                    </div>

                    <div className="cu-row">
                      <div className="cu-dot"><i className="fas fa-phone-alt" /></div>
                      <div>
                        <p className="cu-label">Phone Number</p>
                        <a className="cu-value cu-link" href={`tel:+91${OFFICE.phone}`}>{OFFICE.phone}</a>
                      </div>
                    </div>

                    <div className="cu-row">
                      <div className="cu-dot"><i className="fas fa-envelope" /></div>
                      <div>
                        <p className="cu-label">Email Address</p>
                        <a className="cu-value cu-link" href={`mailto:${OFFICE.email}`}>{OFFICE.email}</a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Branches */}
          <div className="cu-branches-head">
            <h3>Our Branches</h3>
            <div className="cu-bar" />
          </div>

          <div className="cu-branches">
            {BRANCHES.map((b) => (
              <div key={b.name} className="cu-branch">
                <h4>{b.name}</h4>
                <p>{b.place}</p>
              </div>
            ))}
          </div>
        </div>
      </section>
    </main>
  );
}
