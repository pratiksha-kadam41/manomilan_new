import { useEffect, useState } from "react";
import { STORIES } from "../data/stories.js";

// One card: photo slider + names + stars + button
function StoryCard({ story, onOpen }) {
  const [i, setI] = useState(0);
  const n = story.images.length;
  const next = () => setI((i + 1) % n);
  const prev = () => setI((i - 1 + n) % n);

  return (
    <div className="ss-card">
      <div className="ss-photo">
        <div className="ss-slider">
          {story.images.map((src, k) => (
            <img key={src} src={src} alt="" className={`ss-img${k === i ? " on" : ""}`} />
          ))}
          {n > 1 && (
            <>
              <button className="ss-arrow left" onClick={prev} aria-label="Previous">❮</button>
              <button className="ss-arrow right" onClick={next} aria-label="Next">❯</button>
            </>
          )}
        </div>
      </div>

      <div className="ss-body">
        <h2 className="ss-names">
          {story.names[0]} ❤️ {story.names[1]}
        </h2>

        <div className="ss-stars">
          {Array.from({ length: story.stars }).map((_, k) => (
            <i key={k} className="fas fa-star" />
          ))}
        </div>

        {story.desc && <p className="ss-desc">{story.desc}</p>}

        {story.family && (
          <div className="ss-btn-wrap">
            <button className="ss-read" onClick={() => onOpen(story)}>Read Full Story</button>
          </div>
        )}
      </div>
    </div>
  );
}

export default function SuccessStories() {
  const [open, setOpen] = useState(null); // story shown in the popup

  // lock page scroll while the popup is open + close with Esc
  useEffect(() => {
    document.body.style.overflow = open ? "hidden" : "auto";
    const onKey = (e) => e.key === "Escape" && setOpen(null);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = "auto";
    };
  }, [open]);

  return (
    <main>
      <section className="ss-section">
        <div className="ss-wrap">
          <div className="ss-head">
            <h1>❤️ Success Stories</h1>
            <p>
              Every successful marriage begins with the right introduction. Here are some beautiful journeys that started with{" "}
              <span>Aaryans Manomilan.</span>
            </p>
          </div>

          <div className="ss-grid">
            {STORIES.map((s) => (
              <StoryCard key={s.names[0]} story={s} onOpen={setOpen} />
            ))}
          </div>
        </div>

        {/* popup */}
        {open && (
          <div className="ss-modal" onClick={(e) => e.target === e.currentTarget && setOpen(null)}>
            <div className="ss-modal-box">
              <button className="ss-close" onClick={() => setOpen(null)}>✕</button>

              <div className="ss-modal-in">
                <h2 className="ss-modal-title">{open.title || "Success Story"}</h2>

                {open.story.trim() && (
                  <>
                    <div>
                      <h4>📖 Full Story</h4>
                      <p className="ss-modal-story">{open.story}</p>
                    </div>
                    <hr />
                  </>
                )}

                <div>
                  <h4>💌 Family Message</h4>
                  <p className="ss-modal-family">{open.family || "No family message available."}</p>
                </div>
              </div>
            </div>
          </div>
        )}
      </section>
    </main>
  );
}