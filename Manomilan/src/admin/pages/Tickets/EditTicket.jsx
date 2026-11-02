import { useEffect, useRef, useState } from "react";
import { Plus } from "lucide-react";
import { useNavigate } from "react-router-dom";
import AdminLayout from "../../components/AdminLayout";
import { getTicket, getTicketMeta, updateTicket } from "./api/ticketApi";
import { TicketFileInput, TicketItemForm } from "./TicketFormFields";
import { blankTicketItem } from "./ticketFormUtils";
import { emptyTicketMeta, MAX_FILE_MB } from "./ticketOptions";
import "./tickets.css";

const includeOptions = (options, currentValues) => (
  [...new Set([...options, ...currentValues.filter(Boolean)])]
);

export default function EditTicket({ params = {} }) {
  const navigate = useNavigate();
  const bodyRef = useRef(null);
  const itemRefs = useRef([]);
  const [ticket, setTicket] = useState(null);
  const [meta, setMeta] = useState(emptyTicketMeta);
  const [moduleId, setModuleId] = useState("");
  const [subject, setSubject] = useState("");
  const [category, setCategory] = useState("");
  const [commonDescription, setCommonDescription] = useState("");
  const [commonAttachment, setCommonAttachment] = useState(null);
  const [items, setItems] = useState([]);
  const [loadingTicket, setLoadingTicket] = useState(true);
  const [loadingMeta, setLoadingMeta] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [metaError, setMetaError] = useState("");

  useEffect(() => {
    let active = true;

    getTicket(params.id)
      .then((response) => {
        if (!active) return;
        const data = response?.data;
        if (!data) {
          setError("Ticket details were not returned by the server.");
          return;
        }

        setTicket(data);
        setModuleId(String(data.module_id || data.module?.id || ""));
        setSubject(data.subject || "");
        setCategory(data.category || "");
        setCommonDescription(data.description || "");
        setItems((Array.isArray(data.items) ? data.items : []).map((item) => ({
          id: item.id,
          title: item.title || "",
          priority: item.priority || "Medium",
          severity: item.severity || "Medium",
          item_type: item.item_type || "Development",
          description: item.description || "",
          attachment: null,
          attachment_url: item.attachment_url || "",
        })));
      })
      .catch((loadError) => {
        if (active) setError(loadError.message || "Unable to load ticket details.");
      })
      .finally(() => {
        if (active) setLoadingTicket(false);
      });

    getTicketMeta()
      .then((response) => {
        if (active) setMeta({ ...emptyTicketMeta(), ...(response?.data || {}) });
      })
      .catch((loadError) => {
        if (active) {
          setMetaError(loadError.message || "Ticket options could not be loaded. Existing values remain available.");
        }
      })
      .finally(() => {
        if (active) setLoadingMeta(false);
      });

    return () => { active = false; };
  }, [params.id]);

  const options = {
    ...meta,
    modules: ticket?.module && !meta.modules.some((module) => String(module.id) === String(ticket.module.id))
      ? [...meta.modules, ticket.module]
      : meta.modules,
    categories: includeOptions(meta.categories, [category]),
    priorities: includeOptions(meta.priorities, items.map((item) => item.priority)),
    severities: includeOptions(meta.severities, items.map((item) => item.severity)),
    item_types: includeOptions(meta.item_types, items.map((item) => item.item_type)),
  };

  const updateItem = (index, key, value) => {
    setItems((current) => current.map((item, itemIndex) => (
      itemIndex === index ? { ...item, [key]: value } : item
    )));
  };

  const addItem = () => {
    setItems((current) => [...current, blankTicketItem()]);
    window.setTimeout(() => {
      bodyRef.current?.scrollTo({ top: bodyRef.current.scrollHeight, behavior: "smooth" });
      itemRefs.current[itemRefs.current.length - 1]?.focus();
    }, 0);
  };

  const removeItem = (index) => {
    setItems((current) => current.filter((_, itemIndex) => itemIndex !== index));
  };

  const validate = () => {
    if (!moduleId) return "Select a module.";
    if (!subject.trim()) return "Enter a subject.";
    if (!category) return "Select a category.";
    if (!items.length) return "At least one ticket item is required.";
    for (let index = 0; index < items.length; index += 1) {
      if (!items[index].title.trim()) return `Enter a title for item ${index + 1}.`;
      if (!items[index].description.trim()) return `Enter a description for item ${index + 1}.`;
    }
    const files = [commonAttachment, ...items.map((item) => item.attachment)].filter(Boolean);
    if (files.some((file) => file.size > MAX_FILE_MB * 1024 * 1024)) {
      return `Each attachment must be ${MAX_FILE_MB} MB or smaller.`;
    }
    return "";
  };

  const submit = async (event) => {
    event.preventDefault();
    const validationError = validate();
    if (validationError) {
      setError(validationError);
      bodyRef.current?.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }

    setSaving(true);
    setError("");
    const formData = new FormData();
    formData.append("module_id", moduleId);
    formData.append("subject", subject.trim());
    formData.append("category", category);
    formData.append("common_description", commonDescription);
    if (commonAttachment) formData.append("common_attachment", commonAttachment);
    items.forEach((item, index) => {
      if (item.id) formData.append(`items[${index}][id]`, String(item.id));
      ["title", "priority", "severity", "item_type", "description"].forEach((key) => {
        formData.append(`items[${index}][${key}]`, item[key]);
      });
      if (item.attachment) {
        formData.append(`items[${index}][attachment]`, item.attachment);
      }
    });

    try {
      await updateTicket(params.id, formData);
      navigate(`/admin/software-tickets/${params.id}`, {
        state: { notice: "Ticket updated successfully." },
      });
    } catch (submitError) {
      setError(submitError.message || "Unable to update the ticket.");
      bodyRef.current?.scrollTo({ top: 0, behavior: "smooth" });
    } finally {
      setSaving(false);
    }
  };

  const canEdit = ticket?.ticket_status === "Open" && Number(ticket?.status) === 1;
  const ticketPath = `/admin/software-tickets/${params.id}`;

  return (
    <AdminLayout currentPath="/admin/software-tickets">
      <div className="tk-page tk-create-page">
        <form className="tk-create-card" onSubmit={submit}>
          <header className="tk-create-header">
            Edit Ticket{ticket?.ticket_no ? ` · ${ticket.ticket_no}` : ""}
          </header>
          <div className="tk-create-body" ref={bodyRef}>
            {error ? <div className="tk-alert tk-alert-error" role="alert">{error}</div> : null}
            {metaError ? <div className="tk-alert tk-alert-error" role="alert">{metaError}</div> : null}
            {loadingTicket ? (
              <div className="tk-detail-state">Loading ticket for editing…</div>
            ) : ticket ? (
              <>
                {!canEdit ? (
                  <div className="tk-alert tk-alert-error" role="alert">
                    Only active tickets with Open status can be edited.
                  </div>
                ) : null}
                <div className="tk-create-grid">
                  <label className="tk-field">
                    <span>Module <em>*</em></span>
                    <select
                      required
                      value={moduleId}
                      onChange={(event) => setModuleId(event.target.value)}
                      disabled={loadingMeta || !canEdit}
                    >
                      <option value="">Select Module</option>
                      {options.modules.map((module) => (
                        <option key={module.id} value={module.id}>{module.module_name}</option>
                      ))}
                    </select>
                  </label>
                  <label className="tk-field">
                    <span>Subject <em>*</em></span>
                    <input
                      required
                      value={subject}
                      maxLength={255}
                      onChange={(event) => setSubject(event.target.value)}
                      disabled={!canEdit}
                    />
                  </label>
                  <label className="tk-field">
                    <span>Category <em>*</em></span>
                    <select
                      required
                      value={category}
                      onChange={(event) => setCategory(event.target.value)}
                      disabled={!canEdit}
                    >
                      <option value="">Select Category</option>
                      {options.categories.map((value) => <option key={value}>{value}</option>)}
                    </select>
                  </label>
                </div>

                <div className="tk-common-fields">
                  <label className="tk-field tk-common-description">
                    <span>Common Description (Optional)</span>
                    <textarea
                      rows="3"
                      value={commonDescription}
                      onChange={(event) => setCommonDescription(event.target.value)}
                      disabled={!canEdit}
                    />
                  </label>
                  <TicketFileInput
                    label="Common Attachment (Optional)"
                    file={commonAttachment}
                    currentAttachment={ticket.attachment_url
                      ? { url: ticket.attachment_url, name: "Current Attachment" }
                      : null}
                    onChange={(event) => setCommonAttachment(event.target.files?.[0] || null)}
                    disabled={!canEdit}
                  />
                </div>

                <div className="tk-items-heading">
                  <h2>Ticket Items</h2>
                  <button type="button" className="tk-add-item" onClick={addItem} disabled={!canEdit}>
                    <Plus size={15} /> Add Item
                  </button>
                </div>
                <div className="tk-items-list">
                  {items.map((item, index) => (
                    <div key={item.id || `new-${index}`} ref={(element) => { itemRefs.current[index] = element; }}>
                      <TicketItemForm
                        item={item}
                        index={index}
                        options={options}
                        onChange={updateItem}
                        onRemove={removeItem}
                        canRemove={canEdit && items.length > 1}
                        disabled={!canEdit}
                      />
                    </div>
                  ))}
                </div>
              </>
            ) : null}
          </div>
          <footer className="tk-create-footer">
            <button type="button" className="tk-cancel-button" onClick={() => navigate(ticketPath)}>
              Cancel
            </button>
            <button type="submit" className="tk-save-button" disabled={saving || loadingTicket || loadingMeta || !canEdit}>
              {saving ? "Updating…" : "Update Ticket"}
            </button>
          </footer>
        </form>
      </div>
    </AdminLayout>
  );
}
