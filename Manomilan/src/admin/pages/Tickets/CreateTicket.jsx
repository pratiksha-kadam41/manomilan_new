import { useEffect, useRef, useState } from "react";
import { Plus } from "lucide-react";
import { useNavigate } from "react-router-dom";
import AdminLayout from "../../components/AdminLayout";
import { createTicket, getTicketMeta } from "./api/ticketApi";
import { TicketFileInput, TicketItemForm } from "./TicketFormFields";
import { blankTicketItem } from "./ticketFormUtils";
import { emptyTicketMeta, MAX_FILE_MB } from "./ticketOptions";
import "./tickets.css";

export default function CreateTicket() {
  const navigate = useNavigate();
  const bodyRef = useRef(null);
  const itemRefs = useRef([]);
  const [meta, setMeta] = useState(emptyTicketMeta);
  const [moduleId, setModuleId] = useState("");
  const [subject, setSubject] = useState("");
  const [category, setCategory] = useState("");
  const [commonDescription, setCommonDescription] = useState("");
  const [commonAttachment, setCommonAttachment] = useState(null);
  const [items, setItems] = useState([blankTicketItem()]);
  const [loadingMeta, setLoadingMeta] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    let active = true;
    getTicketMeta()
      .then((response) => {
        if (active) {
          setMeta({ ...emptyTicketMeta(), ...(response?.data || {}) });
        }
      })
      .catch((metaError) => {
        if (active) {
          setError(metaError.message || "Unable to load ticket options. Default options are available.");
        }
      })
      .finally(() => {
        if (active) setLoadingMeta(false);
      });
    return () => { active = false; };
  }, []);

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
      ["title", "priority", "severity", "item_type", "description", "attachment"].forEach((key) => {
        if (item[key] !== null) formData.append(`items[${index}][${key}]`, item[key]);
      });
    });

    try {
      await createTicket(formData);
      navigate("/admin/software-tickets", { state: { notice: "Ticket raised successfully." } });
    } catch (submitError) {
      setError(submitError.message || "Unable to raise the ticket.");
      bodyRef.current?.scrollTo({ top: 0, behavior: "smooth" });
    } finally {
      setSaving(false);
    }
  };

  return (
    <AdminLayout currentPath="/admin/software-tickets">
      <div className="tk-page tk-create-page">
        <form className="tk-create-card" onSubmit={submit}>
          <header className="tk-create-header">Create Ticket</header>
          <div className="tk-create-body" ref={bodyRef}>
            {error ? <div className="tk-alert tk-alert-error" role="alert">{error}</div> : null}
            <div className="tk-create-grid">
              <label className="tk-field">
                <span>Module <em>*</em></span>
                <select required value={moduleId} onChange={(event) => setModuleId(event.target.value)} disabled={loadingMeta}>
                  <option value="">Select Module</option>
                  {meta.modules.map((module) => <option key={module.id} value={module.id}>{module.module_name}</option>)}
                </select>
              </label>
              <label className="tk-field">
                <span>Subject <em>*</em></span>
                <input value={subject} maxLength={255} onChange={(event) => setSubject(event.target.value)} />
              </label>
              <label className="tk-field">
                <span>Category <em>*</em></span>
                <select value={category} onChange={(event) => setCategory(event.target.value)}>
                  <option value="">Select Category</option>
                  {meta.categories.map((value) => <option key={value}>{value}</option>)}
                </select>
              </label>
            </div>

            <div className="tk-items-heading">
              <h2>Ticket Items</h2>
              <button type="button" className="tk-add-item" onClick={addItem}><Plus size={15} /> Add Item</button>
            </div>
            <div className="tk-items-list">
              {items.map((item, index) => (
                <div key={index} ref={(element) => { itemRefs.current[index] = element; }}>
                  <TicketItemForm
                    item={item}
                    index={index}
                    options={meta}
                    onChange={updateItem}
                    onRemove={removeItem}
                    canRemove={items.length > 1}
                  />
                </div>
              ))}
            </div>

            <div className="tk-common-fields">
              <label className="tk-field tk-common-description">
                <span>Common Description (Optional)</span>
                <textarea rows="3" value={commonDescription} onChange={(event) => setCommonDescription(event.target.value)} />
              </label>
              <TicketFileInput
                label="Common Attachment (Optional)"
                file={commonAttachment}
                onChange={(event) => setCommonAttachment(event.target.files?.[0] || null)}
              />
            </div>
          </div>
          <footer className="tk-create-footer">
            <button type="button" className="tk-cancel-button" onClick={() => navigate("/admin/software-tickets")}>Cancel</button>
            <button type="submit" className="tk-save-button" disabled={saving || loadingMeta || !meta.modules.length}>
              {saving ? "Saving…" : "Save Ticket"}
            </button>
          </footer>
        </form>
      </div>
    </AdminLayout>
  );
}
