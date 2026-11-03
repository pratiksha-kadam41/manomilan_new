import { useCallback, useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { useLocation, useNavigate } from "react-router-dom";
import {
Plus, Search, Edit, Trash2, Eye, HeartHandshake,
Star, CheckCircle, ImagePlus, X, Loader2,
MapPin, Filter
} from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import SuccessStoryForm from "./SuccessStoryForm.jsx";
import { request, apiMediaUrl } from "../../api.js";
import { useAdminPermission } from "../../permission-context.jsx";
import "./SuccessStories.css";

const EMPTY_FORM = {
groom_name: "",
bride_name: "",
marriage_date: "",
city: "",
state: "",
story_title: "",
short_description: "",
full_story: "",
family_message: "",
rating: 5,
is_featured: false,
display_order: 0,
status: "Active",
groom_photo: null,
bride_photo: null,
couple_photo: null,
wedding_photo: null,
};

const IMAGE_FIELDS = [
{ name: "groom_photo", label: "Groom Photo", required: true },
{ name: "bride_photo", label: "Bride Photo" },
{ name: "couple_photo", label: "Couple Photo" },
{ name: "wedding_photo", label: "Wedding Photo" },
];

export default function SuccessStoriesAdmin() {
const location = useLocation();
const navigate = useNavigate();
const canCreate = useAdminPermission("create", "/admin/success-stories");
const canEdit = useAdminPermission("edit", "/admin/success-stories");
const canDelete = useAdminPermission("delete", "/admin/success-stories");
const canChangeStatus = useAdminPermission("approve", "/admin/success-stories");
const createPage = location.pathname === "/admin/success-stories/create";
const [stories, setStories] = useState([]);
const [loading, setLoading] = useState(true);
const [saving, setSaving] = useState(false);
const [modalOpen, setModalOpen] = useState(false);
const [editingId, setEditingId] = useState(null);
const [form, setForm] = useState(EMPTY_FORM);
const [previews, setPreviews] = useState({});
const [search, setSearch] = useState("");
const [statusFilter, setStatusFilter] = useState("All");
const [page, setPage] = useState(1);
const [pagination, setPagination] = useState(null);
const [error, setError] = useState("");
const previewUrls = useRef({});

const closeModal = useCallback(() => {
  if (saving) return;
  setModalOpen(false);
  setError("");
  setPreviews({});
  Object.values(previewUrls.current).forEach(URL.revokeObjectURL);
  previewUrls.current = {};
}, [saving]);

const loadStories = useCallback(
  currentPage => request(`/admin/success-stories?page=${currentPage}`),
  [],
);

const fetchStories = async (currentPage = page) => {
try {
setLoading(true);

  const response = await loadStories(currentPage);

  const result = response.data;

  setStories(Array.isArray(result) ? result : result?.data || []);
  setPagination(Array.isArray(result) ? null : result);
} catch (err) {
  setError(err.message || "Unable to load stories.");
} finally {
  setLoading(false);
}


};

useEffect(() => {
if (createPage) return undefined;
let isCurrent = true;

loadStories(page)
  .then(response => {
    if (!isCurrent) return;
    const result = response.data;
    setStories(Array.isArray(result) ? result : result?.data || []);
    setPagination(Array.isArray(result) ? null : result);
  })
  .catch(err => {
    if (isCurrent) setError(err.message || "Unable to load stories.");
  })
  .finally(() => {
    if (isCurrent) setLoading(false);
  });

return () => {
  isCurrent = false;
};
}, [createPage, loadStories, page]);

useEffect(() => () => {
  Object.values(previewUrls.current).forEach(URL.revokeObjectURL);
}, []);

useEffect(() => {
  if (!modalOpen) return undefined;

  const previousOverflow = document.body.style.overflow;
  document.body.style.overflow = "hidden";
  const handleKeyDown = event => {
    if (event.key === "Escape") closeModal();
  };
  document.addEventListener("keydown", handleKeyDown);

  return () => {
    document.body.style.overflow = previousOverflow;
    document.removeEventListener("keydown", handleKeyDown);
  };
}, [modalOpen, closeModal]);

const stats = {
total: pagination?.total ?? stories.length,
active: stories.filter(s => s.status === "Active").length,
featured: stories.filter(s => s.is_featured).length,
};

const filteredStories = stories.filter(story => {
const keyword = search.toLowerCase();


const matchesSearch =
  `${story.groom_name} ${story.bride_name} ${story.city} ${story.story_title}`
    .toLowerCase()
    .includes(keyword);

const matchesStatus =
  statusFilter === "All" || story.status === statusFilter;

return matchesSearch && matchesStatus;


});

const openCreate = () => {
Object.values(previewUrls.current).forEach(URL.revokeObjectURL);
previewUrls.current = {};
setEditingId(null);
setForm({ ...EMPTY_FORM });
setPreviews({});
setError("");
navigate("/admin/success-stories/create");
};

const openEdit = async (story) => {
try {
setError("");


  const response = await request(
    `/admin/success-stories/${story.id}`
  );

  const data = response.data;

  setEditingId(data.id);
  Object.values(previewUrls.current).forEach(URL.revokeObjectURL);
  previewUrls.current = {};

  const updatedForm = { ...EMPTY_FORM };

  Object.keys(updatedForm).forEach(key => {
    if (!IMAGE_FIELDS.some(image => image.name === key)) {
      updatedForm[key] = data[key] ?? updatedForm[key];
    }
  });

  IMAGE_FIELDS.forEach(image => {
    updatedForm[image.name] = null;
  });

  setForm(updatedForm);

  const imagePreviews = {};

  IMAGE_FIELDS.forEach(image => {
    if (data[image.name]) {
      imagePreviews[image.name] = apiMediaUrl(
        data[image.name]
      );
    }
  });

  setPreviews(imagePreviews);
  setModalOpen(true);
} catch (err) {
  setError(err.message);
}


};

const handleChange = (event) => {
const { name, value, type, checked } = event.target;


setForm(previous => ({
  ...previous,
  [name]: type === "checkbox" ? checked : value,
}));


};

const handleImage = (event, field) => {
const file = event.target.files?.[0];


if (!file) return;

if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
  setError("Only JPG, PNG and WebP images are allowed.");
  event.target.value = "";
  return;
}

if (file.size > 5 * 1024 * 1024) {
  setError("Image size must not exceed 5 MB.");
  event.target.value = "";
  return;
}

if (previewUrls.current[field]) {
  URL.revokeObjectURL(previewUrls.current[field]);
}
const previewUrl = URL.createObjectURL(file);
previewUrls.current[field] = previewUrl;
setForm(previous => ({
  ...previous,
  [field]: file,
}));

setPreviews(previous => ({
  ...previous,
  [field]: previewUrl,
}));

setError("");


};

const handleSubmit = async (event) => {
event.preventDefault();

if (!editingId && !form.groom_photo) {
  setError("Please upload the required Groom Photo.");
  return;
}

try {
  setSaving(true);
  setError("");

  const payload = new FormData();

  Object.entries(form).forEach(([key, value]) => {
    if (value !== null && value !== undefined) {
      payload.append(
        key,
        value instanceof File
          ? value
          : typeof value === "boolean"
            ? (value ? "1" : "0")
          : String(value)
      );
    }
  });

  if (editingId) {
    await request(`/admin/success-stories/${editingId}`, {
      method: "POST",
      body: payload,
    });
  } else {
    await request("/admin/success-stories", {
      method: "POST",
      body: payload,
    });
  }

  if (createPage) {
    alert("Success story created successfully!");
    navigate("/admin/success-stories");
    return;
  }

  setModalOpen(false);
  setForm({ ...EMPTY_FORM });
  setPreviews({});
  Object.values(previewUrls.current).forEach(URL.revokeObjectURL);
  previewUrls.current = {};
  await fetchStories(page);

  alert(
    editingId
      ? "Success story updated successfully!"
      : "Success story created successfully!"
  );

} catch (err) {
  setError(err.message || "Unable to save success story.");
} finally {
  setSaving(false);
}


};

const toggleStatus = async (story) => {
const newStatus =
story.status === "Active" ? "Inactive" : "Active";


if (!window.confirm(`Change story status to ${newStatus}?`)) {
  return;
}

try {
  await request(`/admin/success-stories/${story.id}/status`, {
    method: "PATCH",
    body: { status: newStatus },
  });

  fetchStories(page);
} catch (err) {
  alert(err.message);
}


};

const deleteStory = async (story) => {
if (!window.confirm(
`Are you sure you want to delete the story of ${story.groom_name} and ${story.bride_name}?`
)) return;


try {
  await request(`/admin/success-stories/${story.id}`, {
    method: "DELETE",
  });

  if (stories.length === 1 && page > 1) {
    setPage(previous => previous - 1);
  } else {
    fetchStories(page);
  }
} catch (err) {
  alert(err.message);
}


};

return (
  <AdminLayout currentPath="/admin/success-stories">
    <div className={`ss-admin ${createPage ? "ss-admin-create-mode" : ""}`}>
      {createPage ? (
        <SuccessStoryForm
          form={form}
          previews={previews}
          saving={saving}
          error={error}
          onChange={handleChange}
          onImage={handleImage}
          onSubmit={handleSubmit}
          onCancel={() => navigate("/admin/success-stories")}
        />
      ) : (
        <>
      <div className="ss-header">
        <div>
          <span className="ss-eyebrow">CONTENT MANAGEMENT</span>
          <h1>Success Stories</h1>
          <p>Manage and celebrate successful Manomilan matches.</p>
        </div>

        {canCreate ? <button className="ss-primary-btn" onClick={openCreate}>
          <Plus size={18} />
          Add Success Story
        </button> : null}
      </div>

      <div className="ss-stats">
        <div className="ss-stat-card">
          <div className="ss-stat-icon blue">
            <HeartHandshake size={23} />
          </div>
          <div>
            <span>Total Stories</span>
            <h2>{stats.total}</h2>
          </div>
        </div>

        <div className="ss-stat-card">
          <div className="ss-stat-icon green">
            <CheckCircle size={23} />
          </div>
          <div>
            <span>Active Stories</span>
            <h2>{stats.active}</h2>
          </div>
        </div>

        <div className="ss-stat-card">
          <div className="ss-stat-icon pink">
            <Star size={23} />
          </div>
          <div>
            <span>Featured Stories</span>
            <h2>{stats.featured}</h2>
          </div>
        </div>
      </div>

      <div className="ss-toolbar">
        <div className="ss-search">
          <Search size={18} />
          <input
            type="text"
            placeholder="Search couples, city or story..."
            value={search}
            onChange={e => setSearch(e.target.value)}
          />
        </div>

        <div className="ss-filter">
          <Filter size={17} />
          <select
            value={statusFilter}
            onChange={e => setStatusFilter(e.target.value)}
          >
            <option value="All">All Status</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
      </div>

      {error && !modalOpen && (
        <div className="ss-error">{error}</div>
      )}

      <div className="ss-gallery-wrap">
        {loading ? (
          <div className="ss-loading">
            <Loader2 className="ss-spin" />
            Loading success stories...
          </div>
        ) : filteredStories.length === 0 ? (
          <div className="ss-empty">
            <HeartHandshake size={45} />
            <h3>No Success Stories Found</h3>
            <p>Create your first success story to get started.</p>
            {canCreate ? <button className="ss-primary-btn" onClick={openCreate}>
              <Plus size={17} /> Add Story
            </button> : null}
          </div>
        ) : (
          <div className="ss-gallery">
            {filteredStories.map(story => {
              const photo = [
                story.couple_photo,
                story.wedding_photo,
                story.groom_photo,
                story.bride_photo,
              ].find(Boolean);

              return (
                <article className="ss-story-card" key={story.id}>
                  <div className="ss-story-photo">
                    <div className="ss-story-photo-empty">
                      <HeartHandshake size={42} />
                      <span>Couple photo not added</span>
                    </div>
                    {photo ? (
                      <img
                        src={apiMediaUrl(photo)}
                        alt={`${story.groom_name} and ${story.bride_name}`}
                        loading="lazy"
                        onError={event => {
                          event.currentTarget.style.display = "none";
                        }}
                      />
                    ) : null}
                    {canChangeStatus ? <button
                      type="button"
                      className={`ss-story-status ${story.status === "Active" ? "active" : "inactive"}`}
                      onClick={() => toggleStatus(story)}
                      title="Change story status"
                    >
                      {story.status}
                    </button> : null}
                    {story.is_featured && (
                      <span className="ss-story-featured">
                        <Star size={12} fill="currentColor" /> Featured
                      </span>
                    )}
                  </div>

                  <div className="ss-story-card-body">
                    <h2>{story.groom_name} <span>♥</span> {story.bride_name}</h2>
                    <div className="ss-story-location">
                      <MapPin size={14} />
                      {story.city || "Location not added"}{story.state ? `, ${story.state}` : ""}
                    </div>
                    <h3>{story.story_title}</h3>
                    {story.short_description && (
                      <p className="ss-story-description">{story.short_description}</p>
                    )}
                    <div className="ss-story-card-footer">
                      <div className="ss-story-stars" aria-label={`${story.rating} out of 5 stars`}>
                        {Array.from({ length: 5 }, (_, index) => (
                          <Star
                            key={index}
                            size={17}
                            fill={index < Number(story.rating) ? "currentColor" : "none"}
                          />
                        ))}
                      </div>
                      <div className="ss-actions">
                        <button title="View story" onClick={() => openEdit(story)}>
                          <Eye size={17} />
                        </button>
                        {canEdit ? <button title="Edit story" onClick={() => openEdit(story)}>
                          <Edit size={17} />
                        </button> : null}
                        {canDelete ? <button title="Delete story" className="delete" onClick={() => deleteStory(story)}>
                          <Trash2 size={17} />
                        </button> : null}
                      </div>
                    </div>
                  </div>
                </article>
              );
            })}
          </div>
        )}
      </div>

      {pagination?.last_page > 1 && (
        <div className="ss-pagination">
          <span>
            Page {pagination.current_page} of {pagination.last_page}
          </span>

          <div>
            <button
              disabled={page <= 1}
              onClick={() => setPage(previous => previous - 1)}
            >
              Previous
            </button>

            <button
              disabled={page >= pagination.last_page}
              onClick={() => setPage(previous => previous + 1)}
            >
              Next
            </button>
          </div>
        </div>
      )}

      {modalOpen && createPortal(
        <div
          className="ss-modal-overlay"
          onClick={closeModal}
          role="presentation"
        >
          <div className="ss-modal" onClick={e => e.stopPropagation()}>
            <div className="ss-modal-header">
          <div className="ss-header-icon-wrap">
            <HeartHandshake size={22} />
          </div>

          <div className="ss-header-copy">
            <h2>
              {editingId ? "Edit Success Story" : "Create Success Story"}
            </h2>
            <p>
              {editingId
                ? "Update this success story and keep it in sync with your listings."
                : "Add a new success story to inspire others."}
            </p>
          </div>

          <button
            type="button"
            className="ss-close-btn"
            onClick={closeModal}
            aria-label="Close form"
            disabled={saving}
          >
            <X size={20} />
              </button>
            </div>

            <form onSubmit={handleSubmit}>
              <div className="ss-form-section">
                <h3>Couple Information</h3>

                <div className="ss-form-grid">
                  <label>
                    Groom Name *
                    <input
                      name="groom_name"
                      value={form.groom_name}
                      onChange={handleChange}
                      required
                    />
                  </label>

                  <label>
                    Bride Name *
                    <input
                      name="bride_name"
                      value={form.bride_name}
                      onChange={handleChange}
                      required
                    />
                  </label>

                  <label>
                    Marriage Date
                    <input
                      type="date"
                      name="marriage_date"
                      value={form.marriage_date?.slice(0, 10) || ""}
                      onChange={handleChange}
                    />
                  </label>

                  <label>
                    City
                    <input
                      name="city"
                      value={form.city}
                      onChange={handleChange}
                    />
                  </label>

                  <label>
                    State
                    <input
                      name="state"
                      value={form.state}
                      onChange={handleChange}
                    />
                  </label>
                </div>
              </div>

              <div className="ss-form-section">
                <h3>Couple Photos</h3>

                <div className="ss-image-grid">
                  {IMAGE_FIELDS.map(image => (
                    <label className="ss-image-upload" key={image.name}>
                      <div className="ss-image-preview">
                        {previews[image.name] ? (
                          <img src={previews[image.name]} alt="" />
                        ) : (
                          <ImagePlus size={28} />
                        )}
                      </div>

                      <strong>
                        {image.label}{image.required ? " *" : ""}
                      </strong>
                      <span>Click to upload (Max 5 MB)</span>

                      <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        aria-label={`${image.label}${image.required ? " required" : ""}`}
                        onChange={e => handleImage(e, image.name)}
                      />
                    </label>
                  ))}
                </div>
              </div>

              <div className="ss-form-section">
                <h3>Success Story Details</h3>

                <div className="ss-form-grid">
                  <label className="full">
                    Story Title *
                    <input
                      name="story_title"
                      value={form.story_title}
                      onChange={handleChange}
                      required
                    />
                  </label>

                  <label className="full">
                    Short Description
                    <textarea
                      name="short_description"
                      rows="3"
                      value={form.short_description}
                      onChange={handleChange}
                    />
                  </label>

                  <label className="full">
                    Full Story
                    <textarea
                      name="full_story"
                      rows="5"
                      value={form.full_story}
                      onChange={handleChange}
                    />
                  </label>

                  <label className="full">
                    Family Message
                    <textarea
                      name="family_message"
                      rows="3"
                      value={form.family_message}
                      onChange={handleChange}
                    />
                  </label>
                </div>
              </div>

              <div className="ss-form-section">
                <h3>Publishing Settings</h3>

                <div className="ss-form-grid">
                  <label>
                    Rating
                    <select
                      name="rating"
                      value={form.rating}
                      onChange={handleChange}
                    >
                      {[1, 2, 3, 4, 5].map(value => (
                        <option key={value} value={value}>
                          {value} Stars
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
                      onChange={handleChange}
                    />
                  </label>

                  <label>
                    Status
                    <select
                      name="status"
                      value={form.status}
                      onChange={handleChange}
                    >
                      <option value="Active">Active</option>
                      <option value="Inactive">Inactive</option>
                    </select>
                  </label>

                  <label className="ss-checkbox-label">
                    <input
                      type="checkbox"
                      name="is_featured"
                      checked={form.is_featured}
                      onChange={handleChange}
                    />
                    Featured Story
                  </label>
                </div>
              </div>

              {error && <div className="ss-error">{error}</div>}

              <div className="ss-modal-footer">
                <button
                  type="button"
                  className="ss-cancel-btn"
                  onClick={closeModal}
                  disabled={saving}
                >
                  Cancel
                </button>

                <button
                  type="submit"
                  className="ss-primary-btn"
                  disabled={saving}
                >
                  {saving ? (
                    <>
                      <Loader2 className="ss-spin" size={17} />
                      Saving...
                    </>
                  ) : (
                    <>
                      <Plus size={17} />
                      {editingId ? "Update Story" : "Create Story"}
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>,
        document.body,
      )}
        </>
      )}
    </div>
  </AdminLayout>
);
}
