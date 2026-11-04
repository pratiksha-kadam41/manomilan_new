import { useEffect, useState } from "react";
import { ArrowRight, HeartHandshake, MapPin, Star } from "lucide-react";
import { apiMediaUrl, request } from "../api.js";

const PHOTO_FIELDS = [
  { key: "couple_photo", label: "Couple" },
  { key: "wedding_photo", label: "Wedding" },
  { key: "groom_photo", label: "Groom" },
  { key: "bride_photo", label: "Bride" },
];

function buildPhotos(story) {
  return PHOTO_FIELDS
    .filter(field => story[field.key])
    .map(field => ({
      label: field.label,
      src: apiMediaUrl(story[field.key]),
    }));
}

function StoryCard({ story }) {
  const photos = buildPhotos(story);
  const [active, setActive] = useState(0);
  const current = photos[Math.min(active, photos.length - 1)];

  const altText = current?.label === "Groom"
    ? story.groom_name
    : current?.label === "Bride"
      ? story.bride_name
      : `${story.groom_name} and ${story.bride_name}`;

  return (
    <article className="mmh-story-card">
      <div className="mmh-story-media">
        {photos.length > 0 ? (
          <img
            className="mmh-story-main"
            src={current.src}
            alt={altText || "Happy couple"}
            loading="lazy"
          />
        ) : (
          <div className="mmh-story-empty">
            <HeartHandshake size={48} aria-hidden="true" />
            <span>Celebrating a beautiful beginning</span>
          </div>
        )}

        <span className="mmh-story-badge">Happy Couple</span>

        {photos.length > 1 && (
          <div className="mmh-story-thumbs" role="group" aria-label="Couple photos">
            {photos.map((photo, index) => (
              <button
                key={`${photo.label}-${index}`}
                type="button"
                className={`mmh-story-thumb${index === active ? " is-active" : ""}`}
                onClick={() => setActive(index)}
                aria-label={`Show ${photo.label} photo`}
                aria-pressed={index === active}
              >
                <img src={photo.src} alt="" loading="lazy" />
              </button>
            ))}
          </div>
        )}
      </div>

      <div className="mmh-story-body">
        <h3 className="mmh-story-names">
          {story.groom_name}
          <span aria-hidden="true">&</span>
          {story.bride_name}
        </h3>

        {(story.city || story.state) && (
          <p className="mmh-story-location">
            <MapPin size={15} aria-hidden="true" />
            {[story.city, story.state].filter(Boolean).join(", ")}
          </p>
        )}

        {story.rating > 0 && (
          <div className="mmh-story-stars" aria-label={`${story.rating} out of 5 stars`}>
            {Array.from({ length: 5 }, (_, index) => (
              <Star
                key={index}
                size={18}
                fill={index < Number(story.rating) ? "currentColor" : "none"}
                strokeWidth={1.8}
                aria-hidden="true"
              />
            ))}
          </div>
        )}

        {story.short_description && (
          <p className="mmh-story-desc">{story.short_description}</p>
        )}

        <a className="mmh-story-link" href="/success-stories">
          Read their story
          <ArrowRight size={16} aria-hidden="true" />
        </a>
      </div>
    </article>
  );
}

export default function HomeSuccessStories() {
  const [stories, setStories] = useState([]);

  useEffect(() => {
    const controller = new AbortController();

    request("/success-stories", { auth: false, signal: controller.signal })
      .then(response => {
        if (!controller.signal.aborted && Array.isArray(response?.data)) {
          setStories(response.data.slice(0, 3));
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) setStories([]);
      });

    return () => controller.abort();
  }, []);

  if (stories.length === 0) return null;

  return (
    <section className="mmh-stories" aria-labelledby="mmh-stories-title">
      <div className="site-container">
        <div className="mmh-stories-head">
          <span className="mmh-stories-kicker">Real Journeys</span>
          <h2 id="mmh-stories-title" className="mmh-sec-title">Success Stories</h2>
          <div className="mmh-divider" aria-hidden="true">
            <i className="bi bi-heart-fill" />
          </div>
          <p className="mmh-sec-sub">
            Every successful marriage begins with the right introduction. Here are a few
            beautiful journeys that started with Aaryans Manomilan.
          </p>
        </div>

        <div className="mmh-stories-grid">
          {stories.map(story => (
            <StoryCard key={story.id} story={story} />
          ))}
        </div>

        <div className="mmh-stories-more">
          <a className="mmh-btn mmh-btn--secondary" href="/success-stories">
            View all success stories
            <ArrowRight size={18} aria-hidden="true" />
          </a>
        </div>
      </div>
    </section>
  );
}
