import React, { useEffect, useState } from "react";
import {
Plus, Search, Edit, Trash2, Eye, HeartHandshake,
Star, Users, CheckCircle, ImagePlus, X, Loader2,
MapPin, Calendar, Filter
} from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import { request, apiMediaUrl } from "../../api.js";
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
{ name: "groom_photo", label: "Groom Photo" },
{ name: "bride_photo", label: "Bride Photo" },
{ name: "couple_photo", label: "Couple Photo" },
{ name: "wedding_photo", label: "Wedding Photo" },
];

export default function SuccessStoriesAdmin() {
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

const fetchStories = async (currentPage = page) => {
try {
setLoading(true);


  const response = await request(
    `/admin/success-stories?page=${currentPage}`
  );

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
fetchStories(page);
}, [page]);

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
setEditingId(null);
setForm({ ...EMPTY_FORM });
setPreviews({});
setError("");
setModalOpen(true);
};

const openEdit = async (story) => {
try {
setError("");


  const response = await request(
    `/admin/success-stories/${story.id}`
  );

  const data = response.data;

  setEditingId(data.id);

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
  return;
}

if (file.size > 5 * 1024 * 1024) {
  setError("Image size must not exceed 5 MB.");
  return;
}

setForm(previous => ({
  ...previous,
  [field]: file,
}));

setPreviews(previous => ({
  ...previous,
  [field]: URL.createObjectURL(file),
}));

setError("");


};

const handleSubmit = async (event) => {
event.preventDefault();


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

  setModalOpen(false);
  setForm({ ...EMPTY_FORM });
  setPreviews({});
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

const closeModal = () => {
setModalOpen(false);
setError("");
setPreviews({});
};

return (
  <AdminLayout currentPath="/admin/success-stories">
    <div className="ss-admin">
      <div className="ss-header">
        <div>
          <span className="ss-eyebrow">CONTENT MANAGEMENT</span>
          <h1>Success Stories</h1>
          <p>Manage and celebrate successful Manomilan matches.</p>
        </div>

        <button className="ss-primary-btn" onClick={openCreate}>
          <Plus size={18} />
          Add Success Story
        </button>
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

      <div className="ss-table-wrapper">
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
            <button className="ss-primary-btn" onClick={openCreate}>
              <Plus size={17} /> Add Story
            </button>
          </div>
        ) : (
          <table className="ss-table">
            <thead>
              <tr>
                <th>COUPLE</th>
                <th>STORY TITLE</th>
                <th>LOCATION</th>
                <th>RATING</th>
                <th>STATUS</th>
                <th>ACTIONS</th>
              </tr>
            </thead>

            <tbody>
              {filteredStories.map(story => (
                <tr key={story.id}>
                  <td>
                    <div className="ss-couple">
                      <img
                        src={
                          apiMediaUrl(story.couple_photo) ||
                          apiMediaUrl(story.groom_photo) ||
                          "/img/aaryan-manomilan-logo.png"
                        }
                        alt=""
                      />
                      <div>
                        <strong>
                          {story.groom_name} & {story.bride_name}
                        </strong>
                        <small>
                          <Calendar size={12} />
                          {story.marriage_date || "Date not added"}
                        </small>
                      </div>
                    </div>
                  </td>

                  <td>
                    <div className="ss-story-title">
                      {story.story_title}
                      {story.is_featured && (
                        <span className="ss-featured">FEATURED</span>
                      )}
                    </div>
                  </td>

                  <td>
                    <span className="ss-location">
                      <MapPin size={13} />
                      {story.city || "-"}, {story.state || "-"}
                    </span>
                  </td>

                  <td>
                    <span className="ss-rating">
                      <Star size={14} fill="currentColor" />
                      {story.rating}/5
                    </span>
                  </td>

                  <td>
                    <button
                      className={`ss-status ${story.status === "Active" ? "active" : "inactive"}`}
                      onClick={() => toggleStatus(story)}
                    >
                      {story.status}
                    </button>
                  </td>

                  <td>
                    <div className="ss-actions">
                      <button title="View story" onClick={() => openEdit(story)}>
                        <Eye size={17} />
                      </button>

                      <button title="Edit story" onClick={() => openEdit(story)}>
                        <Edit size={17} />
                      </button>

                      <button title="Delete story" className="delete" onClick={() => deleteStory(story)}>
                        <Trash2 size={17} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
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

      {modalOpen && (
        <div className="ss-modal-overlay" onClick={closeModal}>
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

          <button className="ss-close-btn" onClick={closeModal}>
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

                      <strong>{image.label}</strong>
                      <span>Click to upload (Max 5 MB)</span>

                      <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
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
        </div>
      )}
    </div>
  </AdminLayout>
);
}
