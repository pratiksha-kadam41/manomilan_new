import {
  ArrowLeft,
  BookOpen,
  Camera,
  CloudUpload,
  Heart,
  Save,
  Settings,
  Users,
} from "lucide-react";

const IMAGE_FIELDS = [
  { name: "groom_photo", label: "Photo 1", required: true },
  { name: "bride_photo", label: "Photo 2" },
  { name: "couple_photo", label: "Photo 3" },
  { name: "wedding_photo", label: "Photo 4" },
];

export default function SuccessStoryForm({
  editing = false,
  form,
  previews,
  saving,
  error,
  onChange,
  onImage,
  onSubmit,
  onCancel,
}) {
  return (
    <div className="ss-create-page">
      <header className="ss-create-banner">
        <div className="ss-create-banner-icon">
          <Heart size={28} fill="currentColor" />
        </div>
        <div>
          <h1>{editing ? "Edit Success Story" : "Create Success Story"}</h1>
          <p>
            {editing
              ? "Update the story details and share this couple’s journey."
              : "Add a new success story to inspire others"}
          </p>
        </div>
      </header>

      <form className="ss-create-form" onSubmit={onSubmit}>
        <section className="ss-create-section">
          <h2><span><Users size={18} /></span>Couple Information</h2>
          <div className="ss-create-grid ss-create-grid-two">
            <label>
              Groom Name <em>*</em>
              <input
                name="groom_name"
                value={form.groom_name}
                onChange={onChange}
                required
                maxLength={255}
              />
            </label>
            <label>
              Bride Name <em>*</em>
              <input
                name="bride_name"
                value={form.bride_name}
                onChange={onChange}
                required
                maxLength={255}
              />
            </label>
          </div>
          <div className="ss-create-grid ss-create-grid-three">
            <label>
              Marriage Date
              <input
                type="date"
                name="marriage_date"
                value={form.marriage_date?.slice(0, 10) || ""}
                onChange={onChange}
              />
            </label>
            <label>
              City
              <input name="city" value={form.city} onChange={onChange} maxLength={255} />
            </label>
            <label>
              State
              <input name="state" value={form.state} onChange={onChange} maxLength={255} />
            </label>
          </div>
        </section>

        <section className="ss-create-section">
          <h2><span><Camera size={18} /></span>Photos</h2>
          <div className="ss-upload-grid">
            {IMAGE_FIELDS.map((image, index) => (
              <label className={`ss-upload-card ${image.required ? "required" : ""}`} key={image.name}>
                <span className="ss-upload-label">
                  {image.label}{image.required && <em>*</em>}
                </span>
                <span className="ss-upload-drop">
                  {previews[image.name] ? (
                    <img src={previews[image.name]} alt={`${image.label} preview`} />
                  ) : (
                    <>
                      <CloudUpload size={38} />
                      <span>Click to upload photo {index + 1}</span>
                    </>
                  )}
                </span>
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  aria-label={`Upload ${image.label}${image.required ? " (required)" : ""}`}
                  onChange={event => onImage(event, image.name)}
                />
              </label>
            ))}
          </div>
          <p className="ss-upload-hint">JPG, PNG or WebP. Maximum file size: 5 MB per photo.</p>
        </section>

        <section className="ss-create-section">
          <h2><span><BookOpen size={18} /></span>Story Details</h2>
          <div className="ss-create-grid">
            <label className="full">
              Story Title <em>*</em>
              <input
                name="story_title"
                value={form.story_title}
                onChange={onChange}
                required
                maxLength={255}
              />
            </label>
            <label className="full">
              Short Description
              <textarea
                name="short_description"
                rows="3"
                value={form.short_description}
                onChange={onChange}
              />
            </label>
            <label className="full">
              Full Story
              <textarea
                name="full_story"
                rows="6"
                value={form.full_story}
                onChange={onChange}
              />
            </label>
            <label className="full">
              Family Message
              <textarea
                name="family_message"
                rows="4"
                value={form.family_message}
                onChange={onChange}
              />
            </label>
          </div>
        </section>

        <section className="ss-create-section ss-publishing-section">
          <h2><span><Settings size={18} /></span>Display Settings</h2>
          <div className="ss-create-grid ss-create-grid-three">
            <label>
              Rating (1–5)
              <select name="rating" value={form.rating} onChange={onChange}>
                {[1, 2, 3, 4, 5].map(value => (
                  <option key={value} value={value}>
                    {value} Star{value === 1 ? "" : "s"}{value === 5 ? " – Excellent" : ""}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Display Order
              <input
                type="number"
                min="0"
                name="display_order"
                value={form.display_order}
                onChange={onChange}
              />
            </label>
            <label>
              Status
              <select name="status" value={form.status} onChange={onChange}>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </label>
          </div>
          <label className="ss-featured-option">
            <input
              type="checkbox"
              name="is_featured"
              checked={form.is_featured}
              onChange={onChange}
            />
            <span><strong>Featured Story</strong> – Display this story prominently on the website</span>
          </label>
        </section>

        {error && <div className="ss-error" role="alert">{error}</div>}

        <footer className="ss-create-actions">
          <button type="button" className="ss-form-cancel" onClick={onCancel} disabled={saving}>
            <ArrowLeft size={16} /> Cancel
          </button>
          <button type="submit" className="ss-form-submit" disabled={saving}>
            <Save size={16} /> {saving ? "Saving..." : editing ? "Update Success Story" : "Create Success Story"}
          </button>
        </footer>
      </form>
    </div>
  );
}
