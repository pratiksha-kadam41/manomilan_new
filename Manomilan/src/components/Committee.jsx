import { useState } from "react";

const D = "District Committee Member";
const C = "Core Committee Member";
const B = "Board of Director";

// To add / remove a person edit only this list. pos = how the photo is cropped.
const MEMBERS = [
  { name: "Swapna Patil", role: D, img: "committee1.jpg" },
  { name: "Mr.Sanjay Kshirsagar", role: C, img: "committee2.jpg" },
  { name: "Mr.Rushikesh Kondekar", role: B, img: "committee3.jpg" },
  { name: "Mr.Mahavir Gosavi", role: D, img: "committee4.jpg" },
  { name: "Mr.Pushkar Koregave", role: C, img: "committee5.jpg" },
  { name: "Mr.Vijay Bage", role: C, img: "committee6.jpg" },
  { name: "Sushama Karande", role: C, img: "committee7.jpg" },
  { name: "Mr.Subhash Dagade", role: "Core Committee", img: "committee8.jpg" },
  { name: "Mr.Sanjaykumar Jain (Bujurge)", role: C, img: "committee9.jpg" },
  { name: "Mr.Arun Bage", role: C, img: "committee10.jpg" },
  { name: "Mr.Laxmikant Bhalerao", role: C, img: "committee11.jpg" },
  { name: "Mr.Vipulchand Dodal", role: C, img: "committee12.jpg" },
  { name: "Mr. Milind Ladge", role: "Founder and Director - Aaryans Manomilan", img: "milind_ladge.jpg" },
  { name: "Mr.Nitin Nakhate", role: B, img: "nitin_nakhate.jpg" },
  { name: "Mr.Sunil Waykos", role: D, img: "sunil_waykos.jpg" },
  { name: "Mr.Balasaheb Ladge", role: "District Commitee Member", img: "balasaheb_ladge.jpg" },
  { name: "Veena Aarwade", role: D, img: "veena_aarwade.jpg", pos: "center" },
  { name: "Mr. Ulhas Chakwate", role: D, img: "ulhas_chakwate.jpg", pos: "center" },
  { name: "Anila Shirish Gotadki", role: D, img: "anila_shirish_gotadki.jpg", pos: "center top" },
  { name: "Mr.Pravin Manikrao Tarte", role: D, img: "pravin_tarte.jpg", pos: "center top" },
  { name: "Mr.Abhinandan Agarthade", role: D, img: "Abhinandan Agarthade.jpeg", pos: "center top" },
  { name: "Mr.Nishant Kandi", role: C, img: "Nishant Kandi.jpeg", pos: "center top" },
];

export default function Committee() {
  const [selected, setSelected] = useState(null);

  return (
    <>
      <section id="committee" className="committee">
        <div className="site-container">
          <h2 className="sec-title">Our Committee Members</h2>
          <p className="sec-sub">The dedicated hearts steering Aaryans Manomilan</p>

          {/* list is rendered twice so the loop has no gap */}
          <div className="marquee-container">
            <div className="marquee-track">
              {[...MEMBERS, ...MEMBERS].map((m, i) => (
                <div key={i} className="member" onClick={() => setSelected(m)}>
                  <div className="member-photo">
                    <img src={`/website/${m.img}`} alt={m.name} style={m.pos ? { objectPosition: m.pos } : undefined} />
                  </div>
                  <h3>{m.name}</h3>
                  <p>{m.role}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Bootstrap modal with the big photo */}
      {selected && (
        <div className="modal fade show d-block member-modal" style={{ background: "rgba(0,0,0,.8)" }}
             onClick={(e) => e.target === e.currentTarget && setSelected(null)}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content">
              <button className="close-x" onClick={() => setSelected(null)} aria-label="Close">
                <i className="bi bi-x-lg" />
              </button>
              <div className="big-photo">
                <img src={`/website/${selected.img}`} alt={selected.name} />
              </div>
              <h3 style={{ fontSize: "1.5rem", lineHeight: "2rem", fontWeight: 700, color: "#111827", marginBottom: ".5rem" }}>{selected.name}</h3>
              <p style={{ fontWeight: 600, color: "#F75270", marginBottom: "1rem" }}>{selected.role}</p>
              <p style={{ fontSize: ".875rem", color: "#4b5563", maxWidth: "24rem" }}>
                Dedicated to bringing hearts together and steering Aaryans Manomilan with trust, guidance, and community spirit.
              </p>
            </div>
          </div>
        </div>
      )}
    </>
  );
}