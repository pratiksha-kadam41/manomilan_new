import { useEffect, useState } from "react";
import { ChevronLeft, ChevronRight, HeartHandshake, LoaderCircle, MapPin, Star } from "lucide-react";
import { request, apiMediaUrl } from "../api.js";

function StoryCard({ story, onOpen }) {
  const [imageIndex, setImageIndex] = useState(0);
  const images = story.images;

  const changeImage = direction => {
    setImageIndex(index => (index + direction + images.length) % images.length);
  };

  return (
    <article className="ss-card">
      <div className="ss-photo">
        {images.length > 0 ? (
          <>
            <div className="ss-slider">
              {images.map((image, index) => (
                <img
                  key={`${image}-${index}`}
                  src={image}
                  alt={`${story.groom_name} and ${story.bride_name}`}
                  className={`ss-img${index === imageIndex ? " on" : ""}`}
                />
              ))}
            </div>
            {images.length > 1 && (
              <>
                <button
                  type="button"
                  className="ss-arrow left"
                  onClick={() => changeImage(-1)}
                  aria-label="Previous couple photo"
                >
                  <ChevronLeft size={22} />
                </button>
                <button
                  type="button"
                  className="ss-arrow right"
                  onClick={() => changeImage(1)}
                  aria-label="Next couple photo"
                >
                  <ChevronRight size={22} />
                </button>
                <div className="ss-photo-count">
                  {imageIndex + 1} / {images.length}
                </div>
              </>
            )}
          </>
        ) : (
          <div className="ss-photo-placeholder">
            <HeartHandshake size={54} />
            <span>Celebrating a beautiful beginning</span>
          </div>
        )}
      </div>

      <div className="ss-body">
        <h2 className="ss-names">
          {story.groom_name} <span aria-hidden="true">❤️</span> {story.bride_name}
        </h2>

        {(story.city || story.state || story.marriage_date) && (
          <div className="ss-meta">
            {(story.city || story.state) && (
              <span><MapPin size={14} />{[story.city, story.state].filter(Boolean).join(", ")}</span>
            )}
            {story.marriage_date && (
              <span>{new Date(`${story.marriage_date}T00:00:00`).toLocaleDateString(undefined, {
                year: "numeric",
                month: "long",
                day: "numeric",
              })}</span>
            )}
          </div>
        )}

        {story.story_title && <h3 className="ss-story-title-public">{story.story_title}</h3>}

        <div className="ss-stars" aria-label={`${story.rating} out of 5 stars`}>
          {Array.from({ length: 5 }, (_, index) => (
            <Star
              key={index}
              size={22}
              fill={index < Number(story.rating) ? "currentColor" : "none"}
              strokeWidth={1.8}
            />
          ))}
        </div>

        {story.short_description && (
          <p className="ss-desc">{story.short_description}</p>
        )}

        {(story.full_story || story.family_message) && (
          <div className="ss-btn-wrap">
            <button type="button" className="ss-read" onClick={() => onOpen(story)}>
              Read Full Story
            </button>
          </div>
        )}
      </div>
    </article>
  );
}

export default function SuccessStories() {
  const [stories, setStories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [reloadKey, setReloadKey] = useState(0);
  const [open, setOpen] = useState(null);

  useEffect(() => {
    const controller = new AbortController();
    request("/success-stories", { auth: false, signal: controller.signal })
      .then(response => {
        if (!Array.isArray(response.data)) {
          throw new Error("The stories response was not in the expected format.");
        }

        setStories(response.data.map(story => ({
          ...story,
          images: [
            story.couple_photo,
            story.wedding_photo,
            story.groom_photo,
            story.bride_photo,
          ].filter(Boolean).map(apiMediaUrl),
        })));
        setLoading(false);
      })
      .catch(loadError => {
        if (controller.signal.aborted) return;
        setError(loadError.message || "Unable to load success stories.");
        setLoading(false);
      });

    return () => controller.abort();
  }, [reloadKey]);

  useEffect(() => {
    if (!open) return undefined;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const onKeyDown = event => {
      if (event.key === "Escape") setOpen(null);
    };
    document.addEventListener("keydown", onKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener("keydown", onKeyDown);
    };
  }, [open]);

  return (
    <main>
      <section className="ss-section">
        <div className="ss-wrap">
          <div className="ss-head">
            <h1><span aria-hidden="true">❤️</span> Success Stories</h1>
            <p>
              Every successful marriage begins with the right introduction. Here are some beautiful journeys that started with{" "}
              <span>Aaryans Manomilan.</span>
            </p>
          </div>

          {loading ? (
            <div className="ss-state" role="status">
              <LoaderCircle className="ss-state-spinner" size={34} />
              <strong>Gathering beautiful stories...</strong>
              <span>Please wait a moment.</span>
            </div>
          ) : error ? (
            <div className="ss-state ss-state-error" role="alert">
              <HeartHandshake size={38} />
              <strong>We couldn’t load the stories</strong>
              <span>{error}</span>
              <button
                type="button"
                className="ss-read"
                onClick={() => {
                  setError("");
                  setLoading(true);
                  setReloadKey(key => key + 1);
                }}
              >
                Try Again
              </button>
            </div>
          ) : stories.length === 0 ? (
            <div className="ss-state">
              <HeartHandshake size={42} />
              <strong>New stories are on their way</strong>
              <span>Check back soon for more beautiful journeys.</span>
            </div>
          ) : (
            <div className="ss-grid">
              {stories.map(story => (
                <StoryCard key={story.id} story={story} onOpen={setOpen} />
              ))}
            </div>
          )}
        </div>

        {open && (
          <div className="ss-public-modal" onClick={event => {
            if (event.target === event.currentTarget) setOpen(null);
          }}>
            <div className="ss-public-modal-box">
              <button
                type="button"
                className="ss-public-close"
                onClick={() => setOpen(null)}
                aria-label="Close story"
              >
                ✕
              </button>
              <div className="ss-public-modal-in">
                <h2 className="ss-public-modal-title">{open.story_title || `${open.groom_name} & ${open.bride_name}`}</h2>

                {open.full_story && (
                  <section>
                    <h4>📖 Their Story</h4>
                    <p className="ss-public-modal-story">{open.full_story}</p>
                  </section>
                )}

                {open.family_message && (
                  <>
                    {open.full_story && <hr />}
                    <section>
                      <h4>💌 A Message from the Family</h4>
                      <p className="ss-public-modal-family">{open.family_message}</p>
                    </section>
                  </>
                )}
              </div>
            </div>
          </div>
        )}
      </section>
    </main>
  );
}
