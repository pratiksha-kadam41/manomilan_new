// "Captured Moments". cols = Bootstrap column classes for each tile.
const PHOTOS = [
  { src: "/website/gallery1.jpeg", alt: "Hast Melap Ceremony", cols: "col-6 col-sm-4 col-lg-3" },
  { src: "/website/gallery2.jpeg", alt: "Engagement", cols: "col-6 col-sm-4 col-lg-3" },
  { src: "/website/gallery3.jpeg", alt: "Pheras Ritual", cols: "col-6 col-sm-4 col-lg-3" },
  { src: "/website/gallery4.jpeg", alt: "Family Gathering", cols: "d-none d-lg-block col-lg-3" },
  { src: "/website/photo501.png", fallback: "/website/gallery5.jpeg", alt: "Vidhi Detail", cols: "col-6 col-sm-4 col-lg-6" },
  { src: "/website/photo101.jpg", alt: "Wedding Party", cols: "col-6 col-sm-4 col-lg-6" },
];

export default function Gallery() {
  return (
    <section id="gallery" className="gallery">
      <div className="site-container">
        <h2 className="sec-title">Captured Moments</h2>
        <p className="sec-sub">Real Stories. Real Connections. Real Happiness.</p>

        <div className="row g-3 g-md-4">
          {PHOTOS.map((p) => (
            <div key={p.src} className={p.cols}>
              <div className="tile lift">
                <img
                  src={p.src}
                  alt={p.alt}
                  onError={(e) => {
                    if (p.fallback && !e.currentTarget.dataset.tried) {
                      e.currentTarget.dataset.tried = "1";
                      e.currentTarget.src = p.fallback;
                    }
                  }}
                />
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}