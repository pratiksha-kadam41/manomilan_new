import { useEffect, useRef, useState } from "react";
import { request } from "../api";
import "./MelavaRegistration.css";

const ICONS = {
  user: "\uD83D\uDC64",
  education: "\uD83C\uDF93",
  work: "\uD83D\uDCBC",
  family: "\uD83D\uDC68\u200D\uD83D\uDC69\u200D\uD83D\uDC67",
  file: "\uD83D\uDCCE",
  heart: "\uD83D\uDC9E",
  eye: "\uD83D\uDC40",
  search: "\uD83D\uDD0D",
  warning: "\u26A0\uFE0F",
  book: "\uD83D\uDCD6",
  edit: "\u270F\uFE0F",
  back: "\u2190",
  next: "\u2192",
  check: "\u2714",
};

const EMPTY_META = {
  is_existing_website_user: "0",
  user_id: "",
  profile_id: "",
  pending_registration_token: "",
};

const EMPTY_SETTINGS = {
  batch_label: "",
  currency: "INR",
  fee: 0,
  booklet_fee: 0,
  qr_image_url: "",
  booklet_available: false,
};

const SUBCASTES = {
  Digambar: [
    "Any", "Dashambar", "Saitwal", "Chaturth", "Pancham", "Dhakad", "Kambhoj",
    "Kasar", "Bagerwal", "Jaiswal", "Agarwal", "Bogar", "Porwar", "Humbad",
    "Visa Humbad", "Khandelwal", "Bannore",
  ],
  Shwetambar: [
    "Any", "Oswal", "Porwal", "Shrimali", "Humad", "Modi", "Khandelwal",
    "Gadiya", "Bagherwal", "Dasha Shrimal", "Visa Shrimali", "Soni", "Lunka",
  ],
};

// Friendly example text shown inside empty inputs (a field's own `p` wins).
const PLACEHOLDERS = {
  username: "10-digit mobile number",
  fullName: "e.g., Rahul Jain",
  motherTongue: "e.g., Hindi",
  language: "e.g., English, Marathi",
  address: "House no., street, area, city, PIN code",
  permanent_address: "House no., street, area, city, PIN code",
  placeOfBirth: "e.g., Pune",
  degreeDiplomaName1: "e.g., B.Com",
  degreeDiplomaName2: "If any",
  specialisation1: "e.g., Finance",
  specialisation2: "If any",
  universityBoardCollege: "e.g., Pune University",
  additionalEducation1: "e.g., CA Inter",
  additionalEducation2: "If any",
  cityOfEducation: "e.g., Pune",
  professionalCity: "e.g., Mumbai",
  designation: "e.g., Manager",
  extraCurricularActivities: "e.g., Music, Dance",
  achievements: "e.g., State-level winner",
  sportsAndFitnessLikings: "e.g., Cricket, Gym",
  instagram: "e.g., @yourname",
  facebook: "Profile name or link",
  fathersName: "Father's full name",
  fatherContactNumber: "10-digit mobile number",
  fathersOccupation: "e.g., Business",
  fathersNativePlace: "e.g., Jaipur",
  mothersName: "Mother's full name",
  motherContactNumber: "10-digit mobile number",
  mothersOccupation: "e.g., Homemaker",
  mothersNativePlace: "e.g., Udaipur",
  noOfBrothers: "0",
  brothersMarried: "0",
  noOfSisters: "0",
  sistersMarried: "0",
  parentsDifferentCaste: "Caste name",
  familyAnnualIncome: "e.g., 1200000",
  profileCreatorName: "Name of the person filling this form",
  referenceName: "Reference person's full name",
  referenceAddress: "Area, city",
  referenceContactNumber: "10-digit mobile number",
  referenceEmail: "name@example.com",
  preferred_education: "e.g., Graduate",
  preferred_working_field: "e.g., IT, Medical",
  expected_income: "e.g., 40000 per month",
  preferred_city: "e.g., Pune",
  other_expectations: "Anything else you would like to share...",
};

const YES_NO = ["Yes", "No"];
const isYes = (value) => value === "Yes";
const greaterThanZero = (value) => Number(value) > 0;
const range = (from, to, pad = false) =>
  Array.from({ length: to - from + 1 }, (_, index) =>
    pad ? String(from + index).padStart(2, "0") : String(from + index),
  );

// F = field. n: name, l: label, t: type, w: grid width (of 12), r: required.
const F = (name, label, type, width, required, extra = {}) => ({
  n: name,
  l: label,
  t: type,
  w: width,
  r: required,
  ...extra,
});

// S = show this field only when predicate(form[dependency]) is true.
const S = (id, fn) => ({ s: [id, fn] });
const DOC_ACCEPT = ".pdf,.jpg,.jpeg,.png";
const MAX_FILE_SIZE = 5 * 1024 * 1024;

const STEPS = [
  {
    t: "Basic Info",
    i: ICONS.user,
    h: "Basic Details",
    s: "Tell us about yourself.",
    f: [
      F("username", "Mobile Number", "tel", 3, 1, { ro: 1, max: 10, h: "Taken from your search" }),
      F("fullName", "Full Name", "text", 3, 1),
      F("source_of_information", "How did you hear about us?", "select", 3, 1, {
        o: ["WhatsApp", "Instagram", "Facebook", "Poster/Flex in Temple", "Outside Banner", "People References", "Call by Manomilan Executive", "Family/Friends"],
      }),
      F("profilePhoto", "Profile Photo", "file", 3, 1, { acc: "image/*", h: "Clear face photo, max 5 MB" }),
      F("full_body_photo", "Full Body Photo", "file", 3, 1, { acc: "image/jpeg,image/png,image/webp", h: "Full-body image, max 5 MB" }),
      F("half_body_photo", "Half Body Photo", "file", 3, 1, { acc: "image/jpeg,image/png,image/webp", h: "Half-body image, max 5 MB" }),
      F("selfie_photo", "Selfie Photo", "file", 3, 1, { acc: "image/jpeg,image/png,image/webp", h: "Clear selfie, max 5 MB" }),
      F("groomOrBride", "Groom / Bride", "select", 3, 1, { o: [["groom", "Groom"], ["bride", "Bride"]] }),
      F("religion", "Religion", "text", 3, 1, { v: "Jain" }),
      F("caste", "Caste", "select", 3, 1, { o: ["N/A", "Shwetambar", "Digambar"] }),
      F("subCaste", "Sub Caste", "select", 3, 1, { sub: "caste", ...S("caste", (value) => value in SUBCASTES) }),
      F("maritalStatus", "Marital Status", "select", 3, 1, { o: ["Unmarried", "Divorced", "Widowed", "Separated"] }),
      F("birthDate", "Birth Date", "date", 3, 1),
      F("height", "Height", "height", 3, 1),
      F("weight", "Weight in kg", "text", 3, 1, { p: "e.g., 70" }),
      F("havingSpecsLenses", "Specs / Lenses", "select", 3, 0, { o: [["Yes", "Specs"], ["No", "Lenses"], ["None", "None"]] }),
      F("bloodGroup", "Blood Group", "select", 3, 1, { o: ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"] }),
      F("motherTongue", "Mother Tongue", "text", 3, 1),
      F("language", "Other Languages", "text", 3),
      F("specialCases", "Special Cases", "text", 3, 0, { p: "If any" }),
      F("knowAboutMyself", "About Myself", "textarea", 9, 0, { p: "Write a short introduction about yourself..." }),
      F("address", "Current Address", "textarea", 6, 1),
      F("permanent_address", "Permanent Address", "textarea", 6, 1),
    ],
  },
  {
    t: "Birth & Education",
    i: ICONS.education,
    h: "Birth & Education",
    s: "Birth time is used for horoscope matching.",
    f: [
      { sec: "Horoscope" },
      F("timeOfBirth", "Time of Birth", "tob", 6, 1, { h: "Hour, minute and AM/PM" }),
      F("placeOfBirth", "Place of Birth", "text", 6, 1),
      { sec: "Education" },
      F("education", "Highest Education", "select", 3, 1, { o: ["10th", "12th", "Graduation", "Post Graduation", "Diploma"] }),
      F("degreeDiplomaName1", "Degree / Diploma 1", "text", 3, 1),
      F("degreeDiplomaName2", "Degree / Diploma 2", "text", 3),
      F("specialisation1", "Specialisation 1", "text", 3),
      F("specialisation2", "Specialisation 2", "text", 3),
      F("universityBoardCollege", "University / College", "text", 3),
      F("additionalEducation1", "Additional Education 1", "text", 3),
      F("additionalEducation2", "Additional Education 2", "text", 3),
      F("cityOfEducation", "City of Education", "text", 3),
    ],
  },
  {
    t: "Work & Hobbies",
    i: ICONS.work,
    h: "Work & Hobbies",
    s: "Your job and what you enjoy.",
    f: [
      { sec: "Professional" },
      F("profession", "Profession", "text", 3, 1, { p: "e.g., Engineer" }),
      F("fieldOfWorking", "Field of Working", "text", 3, 1, { p: "e.g., IT, Medical" }),
      F("organisationWorkingIn", "Organisation", "text", 3, 0, { p: "Company name" }),
      F("professionalCity", "Work City", "text", 3, 1),
      F("designation", "Designation", "text", 3),
      F("lengthOfEmployment", "Length of Employment", "text", 3, 0, { p: "e.g., 2 years" }),
      F("monthlyIncome", "Monthly Income \u20B9", "text", 3, 1, { p: "e.g., 50000" }),
      F("annualIncome", "Annual Income \u20B9", "text", 3, 0, { p: "e.g., 600000" }),
      { sec: "Hobbies" },
      F("hobbies", "Hobbies", "text", 3, 1, { p: "e.g., Reading, Painting" }),
      F("extraCurricularActivities", "Extra Curriculars", "text", 3),
      F("achievements", "Achievements", "text", 3),
      F("sportsAndFitnessLikings", "Sports / Fitness", "text", 3),
      F("cookingSkills", "Cooking Skills", "text", 3, 0, { p: "e.g., Basic, Expert" }),
      F("activeOnSocialSites", "Active on Social Sites?", "select", 3, 0, { o: YES_NO }),
      F("instagram", "Instagram ID", "text", 3, 0, { id: "instagramId", ...S("activeOnSocialSites", isYes) }),
      F("facebook", "Facebook ID", "text", 3, 0, { id: "facebookId", ...S("activeOnSocialSites", isYes) }),
    ],
  },
  {
    t: "Health & Family",
    i: ICONS.family,
    h: "Health & Family",
    s: "Extra boxes appear only when needed.",
    f: [
      { sec: "Medical" },
      F("hasMedicalHistory", "Any medical history?", "select", 12, 1, { o: YES_NO }),
      F("personalMedicalHistory", "Personal Medical History", "text", 4, 0, { p: "If any", ...S("hasMedicalHistory", isYes) }),
      F("currentMedicalProblems", "Current Medical Problems", "text", 4, 0, { p: "If any", ...S("hasMedicalHistory", isYes) }),
      F("medications", "Medications", "text", 4, 0, { p: "If any", ...S("hasMedicalHistory", isYes) }),
      { sec: "Family" },
      F("fathersName", "Father's Name", "text", 3, 1),
      F("fatherAlive", "Father Alive?", "select", 3, 1, { o: YES_NO }),
      F("fatherContactNumber", "Father's Contact", "tel", 3, 1, { max: 10, ...S("fatherAlive", isYes) }),
      F("fathersOccupation", "Father's Occupation", "text", 3, 1, S("fatherAlive", isYes)),
      F("fathersNativePlace", "Father's Native Place", "text", 3),
      F("mothersName", "Mother's Name", "text", 3, 1),
      F("motherAlive", "Mother Alive?", "select", 3, 1, { o: YES_NO }),
      F("motherContactNumber", "Mother's Contact", "tel", 3, 1, { max: 10, ...S("motherAlive", isYes) }),
      F("mothersOccupation", "Mother's Occupation", "text", 3, 1, S("motherAlive", isYes)),
      F("mothersNativePlace", "Mother's Native Place", "text", 3),
      F("noOfBrothers", "Number of Brothers", "number", 3, 1, { min: 0 }),
      F("brothersMarried", "Brothers Married", "number", 3, 1, { min: 0, ...S("noOfBrothers", greaterThanZero) }),
      F("noOfSisters", "Number of Sisters", "number", 3, 1, { min: 0 }),
      F("sistersMarried", "Sisters Married", "number", 3, 1, { min: 0, ...S("noOfSisters", greaterThanZero) }),
      F("parentsIntercasteMarriage", "Parents Intercaste?", "select", 3, 0, { o: YES_NO }),
      F("parentsDifferentCaste", "If yes, which caste", "text", 3),
      F("familyValues", "Family Values", "select", 3, 0, { o: ["Traditional", "Modern", "Liberal"] }),
      F("familyFinancialStatus", "Family Financial Status", "select", 3, 0, { o: ["Lower", "Middle", "Upper"] }),
      F("familyAnnualIncome", "Family Annual Income", "text", 3),
      F("profileCreatorName", "Profile Created By", "text", 3),
      F("profileCreatorRelation", "Creator's Relation", "text", 3, 0, { p: "Self, Father..." }),
    ],
  },
  {
    t: "Reference & Files",
    i: ICONS.file,
    h: "Reference & Documents",
    s: "Each file must be under 5 MB.",
    f: [
      { sec: "Reference Person" },
      F("referenceName", "Name", "text", 3, 1),
      F("referenceRelation", "Relation To You", "text", 3, 1, { p: "e.g., Uncle, Friend" }),
      F("referenceAddress", "Address", "text", 6),
      F("referenceContactNumber", "Contact", "tel", 3, 1, { max: 10 }),
      F("referenceEmail", "Email", "email", 3),
      { sec: "Documents" },
      F("identificationProof", "Identification Proof (Aadhar / PAN)", "file", 4, 1, { acc: DOC_ACCEPT, h: "PDF, JPG, PNG" }),
      F("birthDateProof", "Birth Date Proof (Birth Certificate)", "file", 4, 1, { acc: DOC_ACCEPT, h: "PDF, JPG, PNG" }),
      F("educationProof", "Education Proof", "file", 4, 0, { acc: DOC_ACCEPT, h: "PDF, JPG, PNG" }),
      F("additionalPhotos[]", "Additional Photos (max 4)", "file", 6, 0, { acc: "image/*", id: "additionalPhotos", h: "Select up to 4 photos" }),
      F("candidate_photos[]", "Your Photos (max 5)", "file", 6, 0, { acc: "image/*", id: "candidate_photos", h: "Select up to 5 photos" }),
    ],
  },
  {
    t: "Partner Wishes",
    i: ICONS.heart,
    h: "Partner Preferences",
    s: "All optional. Tell us what you are looking for.",
    f: [
      F("preferred_religion", "Religion", "text", 3, 0, { ro: 1, v: "Jain" }),
      F("preferred_caste", "Preferred Caste", "select", 3, 0, { o: ["Any", "Shwetambar", "Digambar"], id: "pref_caste" }),
      F("preferred_sub_caste", "Preferred Sub Caste", "select", 3, 0, { sub: "preferred_caste", id: "pref_sub_caste", ...S("preferred_caste", (value) => value in SUBCASTES) }),
      F("age_from", "Age From", "select", 3, 0, { o: range(18, 55) }),
      F("age_to", "Age To", "select", 3, 0, { o: range(18, 55) }),
      F("height_from", "Height From", "text", 3, 0, { p: "e.g., 5ft 5in" }),
      F("height_to", "Height To", "text", 3, 0, { p: "e.g., 6ft" }),
      F("preferred_education", "Preferred Education", "text", 3),
      F("working_partner", "Working Partner?", "select", 3, 0, { o: ["Yes", "No", "Any"] }),
      F("preferred_working_field", "Preferred Working Field", "text", 3),
      F("expected_income", "Expected Income", "text", 3),
      F("family_type", "Family Type", "select", 3, 0, { o: ["Joint", "Nuclear"] }),
      F("financial_background", "Financial Background", "select", 3, 0, { o: ["Lower", "Middle", "Upper"] }),
      F("family_values", "Family Values", "select", 3, 0, { o: ["Traditional", "Modern"] }),
      F("preferred_country", "Country", "text", 3, 0, { v: "India" }),
      F("preferred_state", "State", "text", 3, 0, { v: "Maharashtra" }),
      F("preferred_city", "Preferred City", "text", 3),
      F("other_expectations", "Other Expectations", "textarea", 12),
    ],
  },
];

const PREVIEW_STEP = STEPS.length;
const TOTAL_STEPS = STEPS.length + 1;

const isVirtualField = (field) => field.sec || field.t === "file" || field.t === "height" || field.t === "tob";

const INITIAL_FORM = STEPS.reduce(
  (form, step) => {
    step.f.forEach((field) => {
      if (!isVirtualField(field)) form[field.n] = field.v ?? "";
    });
    return form;
  },
  {
    height_ft: "",
    height_in: "0",
    birth_hour: "",
    birth_minute: "",
    birthTime_ampm: "AM",
    booklet_required: false,
    privacyPolicyCheck: false,
    termsCheck: false,
    disclaimer: false,
  },
);

const RESTORE_KEYS = STEPS.flatMap((step) => step.f.filter((field) => !isVirtualField(field)).map((field) => field.n));

const EMPTY_FILES = { profilePhoto: null, full_body_photo: null, half_body_photo: null, selfie_photo: null, candidatePhotos: [], additionalPhotos: [] };
const EMPTY_DOCUMENTS = { identificationProof: null, birthDateProof: null, educationProof: null };
const CONTACT_FIELDS = ["fatherContactNumber", "motherContactNumber", "referenceContactNumber"];

const cleanMobile = (value) => {
  let digits = String(value || "").replace(/\D/g, "");
  if (digits.length === 12 && digits.startsWith("91")) digits = digits.slice(2);
  if (digits.length === 11 && digits.startsWith("0")) digits = digits.slice(1);
  return digits.slice(0, 10);
};

const digitsOnly = (value, max = 15) => String(value || "").replace(/\D/g, "").slice(0, max);

const composeHeight = (feet, inches) => (feet ? `${feet}ft ${inches || 0}in` : "");

const parseHeight = (value) => {
  const match = String(value || "").match(/(\d+)\s*(?:ft|'|feet)?\s*(\d+)/i);
  return match ? { feet: match[1], inches: match[2] } : { feet: "", inches: "0" };
};

const parseTime = (value) => {
  const match = String(value || "").match(/^(\d{1,2}):(\d{2})/);
  if (!match) return null;
  const hour24 = Number(match[1]);
  return {
    hour: String(hour24 % 12 || 12).padStart(2, "0"),
    minute: match[2],
    suffix: hour24 >= 12 ? "PM" : "AM",
  };
};

const composeTime = (hour, minute, ampm) => {
  if (!hour || minute === "") return "";
  let h = Number(hour);
  if (ampm === "AM" && h === 12) h = 0;
  if (ampm === "PM" && h !== 12) h += 12;
  return `${String(h).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
};

const calculateAge = (dateValue) => {
  if (!dateValue) return null;
  const dob = new Date(`${dateValue}T00:00:00`);
  if (Number.isNaN(dob.getTime())) return null;
  const now = new Date();
  let age = now.getFullYear() - dob.getFullYear();
  const month = now.getMonth() - dob.getMonth();
  if (month < 0 || (month === 0 && now.getDate() < dob.getDate())) age -= 1;
  return age;
};

const money = (amount, currency = "INR") => {
  try {
    return new Intl.NumberFormat("en-IN", { style: "currency", currency, maximumFractionDigits: 0 }).format(Number(amount) || 0);
  } catch {
    return String(amount);
  }
};

const settingsFromCampaign = (campaign) => ({
  batch_label: `${campaign.melava_code ? `${campaign.melava_code} - ` : ""}${campaign.title}`,
  currency: campaign.currency,
  fee: campaign.fee,
  booklet_fee: campaign.booklet_fee,
  qr_image_url: campaign.qr_image_url,
  booklet_available: campaign.booklet_available,
});

const fileBucket = (field) => {
  if (field.n === "profilePhoto") return "profilePhoto";
  if (["full_body_photo", "half_body_photo", "selfie_photo"].includes(field.n)) return field.n;
  if (field.n.startsWith("candidate_photos")) return "candidatePhotos";
  if (field.n.startsWith("additionalPhotos")) return "additionalPhotos";
  return field.n;
};

const fieldVisible = (field, form) => {
  if (!field.s) return true;
  const [dependency, predicate] = field.s;
  return predicate(form[dependency]);
};

const optionList = (field, form) => {
  if (field.sub) return (SUBCASTES[form[field.sub]] || []).map((value) => [value, value]);
  return (field.o || []).map((option) => (Array.isArray(option) ? option : [option, option]));
};

const optionLabel = (field, form, value) => {
  const match = optionList(field, form).find(([optionValue]) => String(optionValue) === String(value));
  return match ? match[1] : value;
};

const removeFieldError = (errors, name) => {
  if (!errors[name]) return errors;
  const next = { ...errors };
  delete next[name];
  return next;
};

function FieldError({ message }) {
  return message ? <small className="err">{message}</small> : null;
}

export default function MelavaRegistration() {
  const [form, setForm] = useState({ ...INITIAL_FORM });
  const [websiteChoice, setWebsiteChoice] = useState(null);
  const [files, setFiles] = useState({ ...EMPTY_FILES });
  const [documents, setDocuments] = useState({ ...EMPTY_DOCUMENTS });
  const [meta, setMeta] = useState({ ...EMPTY_META });
  const [settings, setSettings] = useState({ ...EMPTY_SETTINGS });
  const [campaigns, setCampaigns] = useState([]);
  const [selectedCampaignId, setSelectedCampaignId] = useState("");
  const [campaignsLoading, setCampaignsLoading] = useState(true);
  const [campaignsError, setCampaignsError] = useState("");
  const [step, setStep] = useState(0);
  const [stage, setStage] = useState("form");
  const [lookup, setLookup] = useState({ status: "", message: "", tone: "" });
  const [errors, setErrors] = useState({});
  const [submitError, setSubmitError] = useState("");
  const [paymentError, setPaymentError] = useState("");
  const [paymentFile, setPaymentFile] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [result, setResult] = useState(null);
  const objectUrls = useRef([]);

  const heightText = composeHeight(form.height_ft, form.height_in);
  const timeText = composeTime(form.birth_hour, form.birth_minute, form.birthTime_ampm);
  const total = Number(settings.fee) + (form.booklet_required ? Number(settings.booklet_fee) : 0);
  const selectedCampaign = campaigns.find((item) => String(item.id) === selectedCampaignId);

  const makeObjectUrl = (file) => {
    const url = URL.createObjectURL(file);
    objectUrls.current.push(url);
    return url;
  };

  useEffect(() => {
    let alive = true;
    request("/melava-campaigns/active", { auth: false })
      .then((response) => {
        if (!alive) return;
        const activeCampaigns = Array.isArray(response?.data) ? response.data : [];
        setCampaigns(activeCampaigns);
        const requestedId = new URLSearchParams(window.location.search).get("campaign");
        const campaign = activeCampaigns.find((item) => String(item.id) === requestedId) || activeCampaigns[0];
        if (campaign) {
          setSelectedCampaignId(String(campaign.id));
          setSettings(settingsFromCampaign(campaign));
        }
      })
      .catch((error) => {
        if (alive) setCampaignsError(error.message || "Unable to load Melava events.");
      })
      .finally(() => {
        if (alive) setCampaignsLoading(false);
      });
    return () => {
      alive = false;
    };
  }, []);

  useEffect(
    () => () => {
      objectUrls.current.forEach((url) => URL.revokeObjectURL(url));
    },
    [],
  );

  const focusField = (name) => {
    window.setTimeout(() => {
      const wrapper = document.querySelector(`[data-field="${name}"]`);
      const input =
        wrapper?.querySelector("input:not([readonly]):not([type=hidden]), select, textarea") ||
        document.querySelector(`[name="${name}"]`);
      input?.scrollIntoView({ behavior: "smooth", block: "center" });
      input?.focus({ preventScroll: true });
    }, 80);
  };

  const scrollTop = () => window.scrollTo({ top: 0, behavior: "smooth" });

  const update = (name, value) => {
    let nextValue = value;
    if (name === "username") nextValue = cleanMobile(value);
    else if (CONTACT_FIELDS.includes(name)) nextValue = digitsOnly(value);

    setForm((previous) => {
      const next = { ...previous, [name]: nextValue };
      if (name === "username" && nextValue !== previous.username) {
        next.is_existing_website_user = "";
        setWebsiteChoice(null);
      }
      if (name === "caste") next.subCaste = "";
      if (name === "preferred_caste") next.preferred_sub_caste = "";
      return next;
    });

    setErrors((previous) => removeFieldError(previous, name));

    if (name === "username") {
      setLookup({ status: "", message: "", tone: "" });
      setMeta({ ...EMPTY_META });
    }
  };

  const hydrate = (source, { existing = false, username = "" } = {}) => {
    setForm((previous) => {
      const next = { ...previous };

      RESTORE_KEYS.forEach((key) => {
        if (source[key] !== null && source[key] !== undefined && typeof source[key] !== "object") {
          next[key] = String(source[key]);
        }
      });

      if (source.groomOrBride) {
        const gender = String(source.groomOrBride).toLowerCase();
        next.groomOrBride = gender.includes("bride") || gender === "female" ? "bride" : "groom";
      }
      if (source.birthDate) next.birthDate = String(source.birthDate).slice(0, 10);
      if (source.height) {
        const height = parseHeight(source.height);
        if (height.feet) {
          next.height_ft = height.feet;
          next.height_in = height.inches;
        }
      }
      if (source.timeOfBirth) {
        const time = parseTime(source.timeOfBirth);
        if (time) {
          next.birth_hour = time.hour;
          next.birth_minute = time.minute;
          next.birthTime_ampm = source.birthTime_ampm === "PM" ? "PM" : time.suffix;
        }
      }
      if (source.is_booklet_req !== undefined) next.booklet_required = Boolean(source.is_booklet_req);
      if (source.booklet_required !== undefined) next.booklet_required = Boolean(source.booklet_required);
      if (!settings.booklet_available) next.booklet_required = false;
      if (existing) next.is_existing_website_user = "1";
      if (username) next.username = username;

      return next;
    });
  };

  const findCandidate = async () => {
    const mobile = cleanMobile(form.username);

    if (!/^[6-9]\d{9}$/.test(mobile)) {
      setErrors((previous) => ({ ...previous, username: "Enter a valid 10-digit mobile number." }));
      focusField("username");
      return;
    }

    setLookup({ status: "loading", message: "Checking your registration...", tone: "" });
    setErrors((previous) => removeFieldError(previous, "username"));

    try {
      const data = await request(
        `/melava-registrations/check?mobile=${mobile}&melava_campaign_id=${selectedCampaignId}`,
        { auth: false },
      );

      if (data.type === "website_user") {
        setWebsiteChoice("existing");
        hydrate(
          {
            ...(data.profile || {}),
            ...(data.melava_registration || {}),
            ...(data.preference || {}),
            is_booklet_req: data.registration?.is_booklet_req,
          },
          { existing: true, username: data.user.username },
        );
        setFiles((previous) => ({
          ...previous,
          profilePhoto: data.profile_photo_url
            ? { preview: data.profile_photo_url, existing: true }
            : previous.profilePhoto,
          full_body_photo: data.full_body_photo_url
            ? { preview: data.full_body_photo_url, existing: true }
            : previous.full_body_photo,
          half_body_photo: data.half_body_photo_url
            ? { preview: data.half_body_photo_url, existing: true }
            : previous.half_body_photo,
          selfie_photo: data.selfie_photo_url
            ? { preview: data.selfie_photo_url, existing: true }
            : previous.selfie_photo,
        }));
        setDocuments((previous) => ({
          ...previous,
          identificationProof: data.identification_proof_url
            ? { preview: data.identification_proof_url, existing: true }
            : previous.identificationProof,
          birthDateProof: data.birth_date_proof_url
            ? { preview: data.birth_date_proof_url, existing: true }
            : previous.birthDateProof,
          educationProof: data.education_proof_url
            ? { preview: data.education_proof_url, existing: true }
            : previous.educationProof,
        }));
        setFiles((previous) => ({
          ...previous,
          candidatePhotos: (data.candidate_photos_urls || []).map((preview) => ({ preview, existing: true })),
          additionalPhotos: (data.additional_photos_urls || []).map((preview) => ({ preview, existing: true })),
        }));
        setMeta({
          is_existing_website_user: "1",
          user_id: String(data.user.id || ""),
          profile_id: String(data.user.profile_id || ""),
          pending_registration_token: "",
        });
        setLookup({
          status: "existing",
          message: data.has_registration
            ? "Your existing details are restored. Submitting will update your Melava registration for this batch."
            : "Your website details are restored. Complete the remaining information to register for Melava.",
          tone: "ok",
        });
      } else if (data.type === "pending_registration" && data.pending_registration) {
        const pending = data.pending_registration;
        setWebsiteChoice(pending.is_existing_website_user ? "existing" : "new");
        const pendingData = pending.registration_data || {};
        hydrate(pendingData, {
          existing: Boolean(pending.is_existing_website_user),
          username: pendingData.username || mobile,
        });
        if (pending.profile_photo_url) {
          setFiles((previous) => ({
            ...previous,
            profilePhoto: { preview: pending.profile_photo_url, existing: true },
          }));
        }
        setFiles((previous) => ({
          ...previous,
          full_body_photo: pending.full_body_photo_url
            ? { preview: pending.full_body_photo_url, existing: true }
            : previous.full_body_photo,
          half_body_photo: pending.half_body_photo_url
            ? { preview: pending.half_body_photo_url, existing: true }
            : previous.half_body_photo,
          selfie_photo: pending.selfie_photo_url
            ? { preview: pending.selfie_photo_url, existing: true }
            : previous.selfie_photo,
        }));
        setDocuments((previous) => ({
          ...previous,
          identificationProof: pending.identification_proof_url
            ? { preview: pending.identification_proof_url, existing: true }
            : previous.identificationProof,
          birthDateProof: pending.birth_date_proof_url
            ? { preview: pending.birth_date_proof_url, existing: true }
            : previous.birthDateProof,
          educationProof: pending.education_proof_url
            ? { preview: pending.education_proof_url, existing: true }
            : previous.educationProof,
        }));
        setMeta({
          is_existing_website_user: pending.is_existing_website_user ? "1" : "0",
          user_id: String(pending.user_id || ""),
          profile_id: String(pending.profile_id || ""),
          pending_registration_token: pending.token || "",
        });
        setLookup({
          status: "existing",
          message: "Your saved registration was found and restored. Please review it.",
          tone: "ok",
        });
      } else {
        setWebsiteChoice("new");
        setForm((previous) => ({ ...previous, username: mobile, is_existing_website_user: "0" }));
        setMeta({ ...EMPTY_META });
        setLookup({
          status: "new",
          message: data.message || "No previous profile found. You can continue as a new candidate.",
          tone: "ok",
        });
      }
    } catch (error) {
      setLookup({
        status: "error",
        message: error.message || "Unable to fetch registration details.",
        tone: "er",
      });
    }
  };

  const chooseWebsiteRegistration = (isRegistered) => {
    setWebsiteChoice(isRegistered ? "existing" : "new");
    setErrors((previous) => removeFieldError(previous, "username"));
    setForm((previous) => ({
      ...previous,
      is_existing_website_user: isRegistered ? "1" : "0",
    }));
    setMeta((previous) => ({
      ...previous,
      is_existing_website_user: isRegistered ? "1" : "0",
      user_id: isRegistered ? previous.user_id : "",
      profile_id: isRegistered ? previous.profile_id : "",
    }));
    setLookup({
      status: "",
      message: isRegistered
        ? "Website registration selected. Enter your mobile number, then click Fetch Details."
        : "New candidate selected. Enter your mobile number and continue.",
      tone: "ok",
    });
  };

  const handleFileChange = (field, event) => {
    const selected = Array.from(event.target.files || []);
    event.target.value = "";
    if (!selected.length) return;

    if (selected.some((file) => file.size > MAX_FILE_SIZE)) {
      setErrors((previous) => ({ ...previous, [field.n]: "Each file must be 5 MB or smaller." }));
      return;
    }

    const bucket = fileBucket(field);

    if (bucket === "profilePhoto") {
      setFiles((previous) => ({ ...previous, profilePhoto: { file: selected[0], preview: makeObjectUrl(selected[0]) } }));
    } else if (["full_body_photo", "half_body_photo", "selfie_photo"].includes(bucket)) {
      setFiles((previous) => ({ ...previous, [bucket]: { file: selected[0], preview: makeObjectUrl(selected[0]) } }));
    } else if (bucket === "additionalPhotos" || bucket === "candidatePhotos") {
      const max = bucket === "candidatePhotos" ? 5 : 4;
      if ((files[bucket] || []).length + selected.length > max) {
        setErrors((previous) => ({ ...previous, [field.n]: `You can add up to ${max} photos only.` }));
        return;
      }
      const items = selected.map((file) => ({ file, preview: makeObjectUrl(file) }));
      setFiles((previous) => ({ ...previous, [bucket]: [...(previous[bucket] || []), ...items] }));
    } else {
      setDocuments((previous) => ({ ...previous, [bucket]: { file: selected[0], preview: makeObjectUrl(selected[0]) } }));
    }

    setErrors((previous) => removeFieldError(previous, field.n));
  };

  const removeFile = (field) => {
    const bucket = fileBucket(field);
    if (["profilePhoto", "full_body_photo", "half_body_photo", "selfie_photo"].includes(bucket)) setFiles((previous) => ({ ...previous, [bucket]: null }));
    else if (bucket === "additionalPhotos" || bucket === "candidatePhotos") setFiles((previous) => ({ ...previous, [bucket]: [] }));
    else setDocuments((previous) => ({ ...previous, [bucket]: null }));
  };

  const fileItems = (field) => {
    const bucket = fileBucket(field);
    if (["profilePhoto", "full_body_photo", "half_body_photo", "selfie_photo"].includes(bucket)) return files[bucket] ? [files[bucket]] : [];
    if (bucket === "additionalPhotos") return files.additionalPhotos;
    if (bucket === "candidatePhotos") return files.candidatePhotos;
    return documents[bucket] ? [documents[bucket]] : [];
  };

  const validateStep = (index) => {
    if (index >= PREVIEW_STEP) return true;
    const nextErrors = {};

    if (index === 0) {
      if (!["existing", "new"].includes(lookup.status)) {
        nextErrors.username = lookup.status === "error" ? lookup.message : "Search your mobile number first.";
      }
      if (!/^[6-9]\d{9}$/.test(cleanMobile(form.username))) {
        nextErrors.username = "Enter a valid 10-digit mobile number.";
      }
      if (form.birthDate && Number(calculateAge(form.birthDate)) < 18) {
        nextErrors.birthDate = "The candidate must be at least 18 years old.";
      }
    }

    STEPS[index].f.forEach((field) => {
      if (field.sec || !field.r || !fieldVisible(field, form)) return;

      if (field.t === "file") {
        if (!fileItems(field).length) nextErrors[field.n] = "Please upload this file.";
      } else if (field.t === "height") {
        if (!heightText) nextErrors.height = "Select the candidate's height.";
      } else if (field.t === "tob") {
        if (!form.birth_hour || form.birth_minute === "") nextErrors.timeOfBirth = "Time of birth is required.";
      } else if (!String(form[field.n] ?? "").trim()) {
        nextErrors[field.n] = "This field is required.";
      }
    });

    CONTACT_FIELDS.forEach((key) => {
      if (form[key] && String(form[key]).length !== 10) nextErrors[key] = "Enter a valid 10-digit mobile number.";
    });

    const firstError = Object.keys(nextErrors)[0];
    if (firstError) {
      setErrors((previous) => ({ ...previous, ...nextErrors }));
      setStep(index);
      focusField(firstError);
      return false;
    }
    return true;
  };

  const validateAll = () => {
    for (let index = 0; index < STEPS.length; index += 1) {
      if (!validateStep(index)) return false;
    }

    const policyErrors = {};
    if (!form.privacyPolicyCheck) policyErrors.privacyPolicyCheck = "Please accept the privacy policy.";
    if (!form.termsCheck) policyErrors.termsCheck = "Please accept the terms and conditions.";

    if (Object.keys(policyErrors).length) {
      setErrors((previous) => ({ ...previous, ...policyErrors }));
      setStep(PREVIEW_STEP);
      scrollTop();
      focusField(Object.keys(policyErrors)[0]);
      return false;
    }
    return true;
  };

  const goToStep = (nextStep) => {
    setStep(nextStep);
    scrollTop();
  };

  const goNext = () => {
    setErrors({});
    if (!validateStep(step)) return;
    goToStep(Math.min(PREVIEW_STEP, step + 1));
  };

  const goBack = () => {
    if (step === 0) return;
    setErrors({});
    goToStep(step - 1);
  };

  const jumpToStep = (target) => {
    if (target > step) {
      for (let index = step; index < target; index += 1) {
        if (!validateStep(index)) return;
      }
    }
    setErrors({});
    goToStep(target);
  };

  const buildPendingPayload = () => {
    const payload = buildPayload();
    payload.delete("payment_screenshot");
    payload.append("mobile", cleanMobile(form.username));
    payload.append("token", meta.pending_registration_token || "");

    const existingPaths = {};
    if (files.profilePhoto?.existing) existingPaths.profilePhoto = files.profilePhoto.preview;
    ["full_body_photo", "half_body_photo", "selfie_photo"].forEach((field) => {
      if (files[field]?.existing) existingPaths[field] = files[field].preview;
    });
    Object.entries(documents).forEach(([field, item]) => {
      if (item?.existing) existingPaths[field] = item.preview;
    });

    payload.append("registration_data", JSON.stringify({
      ...form,
      username: cleanMobile(form.username),
      is_existing_website_user: meta.is_existing_website_user || "0",
      ...existingPaths,
    }));
    return payload;
  };

  const startPayment = async () => {
    if (!validateAll()) return;
    setPaymentError("");
    setSubmitError("");
    setSubmitting(true);
    try {
      const response = await request("/melava-registrations/draft", {
        method: "POST",
        body: buildPendingPayload(),
        auth: false,
      });
      setMeta((previous) => ({ ...previous, pending_registration_token: response.token || "" }));
      setStage("payment");
      scrollTop();
    } catch (error) {
      setSubmitError(error?.message || "Unable to save registration details. Please try again.");
      setPaymentError(error?.message || "Unable to save registration details. Please try again.");
    } finally {
      setSubmitting(false);
    }
  };

  const buildPayload = () => {
    const payload = new FormData();

    payload.append("melava_campaign_id", selectedCampaignId);
    Object.entries(form).forEach(([key, value]) => {
      if (["height_ft", "height_in", "is_existing_website_user"].includes(key)) return;
      if (typeof value === "boolean") payload.append(key, value ? "1" : "0");
      else if (value !== null && value !== undefined) payload.append(key, value);
    });

    Object.entries(meta).forEach(([key, value]) => payload.append(key, value ?? ""));
    payload.append("height", heightText);
    if (timeText) payload.append("timeOfBirth", timeText);

    if (files.profilePhoto?.file) payload.append("profilePhoto", files.profilePhoto.file);
    ["full_body_photo", "half_body_photo", "selfie_photo"].forEach((field) => {
      if (files[field]?.file) payload.append(field, files[field].file);
    });
    files.candidatePhotos.forEach((item) => payload.append("candidate_photos[]", item.file));
    files.additionalPhotos.forEach((item) => payload.append("additionalPhotos[]", item.file));
    Object.entries(documents).forEach(([key, item]) => {
      if (item?.file) payload.append(key, item.file);
    });
    if (paymentFile?.file) payload.append("payment_screenshot", paymentFile.file);

    return payload;
  };

  const submitPayment = async () => {
    setPaymentError("");
    setSubmitError("");

    if (!selectedCampaignId) {
      setPaymentError("Please select an active Melava event.");
      return;
    }
    if (!paymentFile?.file) {
      setPaymentError("Please upload the payment screenshot.");
      return;
    }

    setSubmitting(true);
    try {
      const response = await request("/melava-registrations", { method: "POST", body: buildPayload(), auth: false });
      setResult(response);
      setStage("success");
      scrollTop();
    } catch (error) {
      const serverErrors = {};
      Object.entries(error.errors || {}).forEach(([key, value]) => {
        serverErrors[key] = Array.isArray(value) ? value[0] : value;
      });
      setErrors(serverErrors);

      const firstKey = Object.keys(serverErrors)[0];
      const targetStep = STEPS.findIndex((item) => item.f.some((field) => field.n === firstKey));

      if (firstKey && targetStep >= 0) {
        setStage("form");
        setStep(targetStep);
        setSubmitError(error.message || "Please review the highlighted fields.");
        focusField(firstKey);
      } else {
        setPaymentError(serverErrors.payment_screenshot || error.message || "Unable to submit the registration.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  const handlePaymentFile = (event) => {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;
    if (file.size > MAX_FILE_SIZE) {
      setPaymentError("The screenshot must be 5 MB or smaller.");
      return;
    }
    setPaymentFile({ file, preview: makeObjectUrl(file) });
    setPaymentError("");
  };

  const resetAll = () => {
    objectUrls.current.forEach((url) => URL.revokeObjectURL(url));
    objectUrls.current = [];
    setForm({ ...INITIAL_FORM });
    setWebsiteChoice(null);
    setFiles({ ...EMPTY_FILES });
    setDocuments({ ...EMPTY_DOCUMENTS });
    setMeta({ ...EMPTY_META });
    setStep(0);
    setStage("form");
    setLookup({ status: "", message: "", tone: "" });
    setErrors({});
    setSubmitError("");
    setPaymentError("");
    setPaymentFile(null);
    setSubmitting(false);
    setResult(null);
  };

  const selectCampaign = (id) => {
    const campaign = campaigns.find((item) => String(item.id) === String(id));
    if (!campaign) {
      setSelectedCampaignId("");
      setLookup({ status: "", message: "", tone: "" });
      return;
    }
    setSelectedCampaignId(String(campaign.id));
    setSettings(settingsFromCampaign(campaign));
    if (!campaign.booklet_available) update("booklet_required", false);
    setLookup({ status: "", message: "", tone: "" });
  };

  const renderFileField = (field) => {
    const items = fileItems(field);
    const bucket = fileBucket(field);
    const isMultiple = bucket === "additionalPhotos" || bucket === "candidatePhotos";

    return (
      <div className={`f w${field.w}`} key={field.n} data-field={field.n}>
        <label>{field.l}{field.r ? <b>*</b> : null}</label>
        <input
          id={field.id || bucket}
          type="file"
          accept={field.acc}
          multiple={isMultiple}
          onChange={(event) => handleFileChange(field, event)}
        />
        {field.h ? <small>{field.h}</small> : null}
        {items.length ? (
          <div className="ph">
            {items.map((item, index) => (
              <span className="ph-item" key={`${field.n}-${index}`}>
                {item.file ? (
                  item.file.type.startsWith("image/") ? <img src={item.preview} alt="" /> : <span className="doc-name">{item.file.name}</span>
                ) : ["profilePhoto", "full_body_photo", "half_body_photo", "selfie_photo", "candidatePhotos", "additionalPhotos"].includes(bucket) ? (
                  <img src={item.preview} alt="Existing uploaded file" />
                ) : item.preview ? (
                  <a className="doc-name" href={item.preview} target="_blank" rel="noreferrer">View uploaded document</a>
                ) : null}
              </span>
            ))}
            <button type="button" className="mini" onClick={() => removeFile(field)}>Remove</button>
          </div>
        ) : null}
        <FieldError message={errors[field.n]} />
      </div>
    );
  };

  const renderField = (field) => {
    if (field.sec) return <div className="sec" key={field.sec}>{field.sec}</div>;
    if (!fieldVisible(field, form)) return null;
    if (field.t === "file") return renderFileField(field);

    const key = field.n;
    const id = field.id || key;
    const placeholder = field.p || PLACEHOLDERS[key] || "";
    const label = <label htmlFor={id}>{field.l}{field.r ? <b>*</b> : null}</label>;

    if (field.t === "height") {
      return (
        <div className="f w3" key={key} data-field="height">
          <label>{field.l}<b>*</b></label>
          <div className="r2">
            <input type="number" min="3" max="8" placeholder="Feet" aria-label="Feet" value={form.height_ft} onChange={(event) => update("height_ft", event.target.value)} />
            <input type="number" min="0" max="11" placeholder="Inches" aria-label="Inches" value={form.height_in} onChange={(event) => update("height_in", event.target.value)} />
          </div>
          <small>Feet and inches</small>
          <FieldError message={errors.height} />
        </div>
      );
    }

    if (field.t === "tob") {
      return (
        <div className="f w6" key={key} data-field="timeOfBirth">
          <label>{field.l}<b>*</b></label>
          <div className="r3">
            <select aria-label="Hour" value={form.birth_hour} onChange={(event) => update("birth_hour", event.target.value)}>
              <option value="">Hour</option>
              {range(1, 12, true).map((hour) => <option key={hour} value={hour}>{hour}</option>)}
            </select>
            <select aria-label="Minute" value={form.birth_minute} onChange={(event) => update("birth_minute", event.target.value)}>
              <option value="">Min</option>
              {range(0, 59, true).map((minute) => <option key={minute} value={minute}>{minute}</option>)}
            </select>
            <select aria-label="AM or PM" value={form.birthTime_ampm} onChange={(event) => update("birthTime_ampm", event.target.value)}>
              <option value="AM">AM</option>
              <option value="PM">PM</option>
            </select>
          </div>
          <small>{field.h}</small>
          <FieldError message={errors.timeOfBirth} />
        </div>
      );
    }

    if (field.t === "select") {
      return (
        <div className={`f w${field.w}`} key={key} data-field={key}>
          {label}
          <select id={id} name={key} value={form[key] ?? ""} onChange={(event) => update(key, event.target.value)} disabled={Boolean(field.sub && !form[field.sub])}>
            <option value="">{field.sub && !form[field.sub] ? "Choose caste first" : "Choose one"}</option>
            {optionList(field, form).map(([value, text]) => <option key={value} value={value}>{text}</option>)}
          </select>
          <FieldError message={errors[key]} />
        </div>
      );
    }

    if (field.t === "textarea") {
      return (
        <div className={`f w${field.w}`} key={key} data-field={key}>
          {label}
          <textarea id={id} name={key} rows="3" placeholder={placeholder} value={form[key] ?? ""} onChange={(event) => update(key, event.target.value)} />
          <FieldError message={errors[key]} />
        </div>
      );
    }

    return (
      <div className={`f w${field.w}`} key={key} data-field={key}>
        {label}
        <input
          id={id}
          name={key}
          type={field.t}
          placeholder={placeholder}
          value={form[key] ?? ""}
          readOnly={Boolean(field.ro)}
          maxLength={field.max}
          inputMode={field.max || field.t === "number" ? "numeric" : undefined}
          min={field.min}
          onChange={(event) => update(key, event.target.value)}
        />
        {field.h ? <small>{field.h}</small> : null}
        <FieldError message={errors[key]} />
      </div>
    );
  };

  const renderPreviewValue = (field) => {
    if (field.t === "height") return heightText || "\u2014";
    if (field.t === "tob") return timeText ? `${form.birth_hour}:${form.birth_minute} ${form.birthTime_ampm}` : "\u2014";
    if (field.t === "select") return form[field.n] ? optionLabel(field, form, form[field.n]) : "\u2014";
    if (field.t === "file") {
      const items = fileItems(field);
      if (!items.length) return "\u2014";
      return (
        <div className="ph">
          {items.map((item, index) =>
            item.file?.type ? (
              item.file.type.startsWith("image/") ? (
                <img key={`${field.n}-${index}`} src={item.preview} alt="" />
              ) : (
                <span className="doc-name" key={`${field.n}-${index}`}>{item.file.name}</span>
              )
            ) : (
              <img key={`${field.n}-${index}`} src={item.preview} alt="" />
            ),
          )}
        </div>
      );
    }
    return String(form[field.n] ?? "").trim() || "\u2014";
  };

  if (campaignsLoading) {
    return (
      <main className="melava-page">
        <div className="wrap"><section className="card">Loading open Melava events...</section></div>
      </main>
    );
  }

  if (campaignsError || !campaigns.length) {
    return (
      <main className="melava-page">
        <div className="wrap">
          <section className="card">
            <h2>{campaignsError ? "Unable to load events" : "No open Melava events"}</h2>
            <p className="sub">{campaignsError || "Registration will appear here when the admin opens a Melava event."}</p>
          </section>
        </div>
      </main>
    );
  }

  if (result) {
    const data = result.data || {};
    return (
      <main className="melava-page">
        <div className="wrap">
          <div className="hero">
            <div className="hero-copy">
              <h1>Registration Submitted</h1>
              <p>Your Melava registration has been received. Payment is pending admin verification.</p>
              <span className="must">{settings.batch_label}</span>
            </div>
          </div>
          <section className="card success-card">
            <span className="success-icon">{ICONS.check}</span>
            <h2>{result.message || "You're registered for Melava!"}</h2>
            <div className="summary">
              <div><span>Registration number</span><strong>{data.registration_number || "\u2014"}</strong></div>
              <div><span>Melava serial number</span><strong>{data.serial_no || "Will be allotted"}</strong></div>
              <div><span>Melava</span><strong>{data.melava_title || settings.batch_label}</strong></div>
              <div><span>Booklet</span><strong>{data.is_booklet_req ? "Requested" : "Not requested"}</strong></div>
              <div><span>Payment</span><strong>{data.payment_verified ? "Verified" : "Pending verification"}</strong></div>
            </div>
            {result.credentials ? (
              <div className="al ok">
                <b>Your login is ready.</b>
                <p>
                  Use your mobile number <b>{result.credentials.username}</b> and the temporary password{" "}
                  <b>{result.credentials.password}</b> to sign in. Please change the password after your first login.
                </p>
              </div>
            ) : (
              <div className="al ok">Your existing website account has been linked to this Melava registration.</div>
            )}
            <div className="actions">
              <a href="/login" className="btn p">Go to login</a>
              <button type="button" className="btn g" onClick={resetAll}>Register another candidate</button>
            </div>
          </section>
        </div>
      </main>
    );
  }

  if (stage === "payment") {
    return (
      <main className="melava-page">
        <div className="wrap">
          <div className="hero compact">
            <div className="hero-copy">
              <h1>Complete Your Payment</h1>
              <p>Scan the QR code, pay the registration fee, then upload the payment screenshot.</p>
              <span className="must">{settings.batch_label}</span>
            </div>
          </div>

          <section className="card pay-card">
            <h2>Payment Details</h2>
            <p className="sub">Your registration will be saved with payment pending admin verification.</p>

            <div className="pay-grid">
              <div className="fee-box">
                <div className="fee-row"><span>Registration fee</span><strong>{money(settings.fee, settings.currency)}</strong></div>
                {form.booklet_required ? (
                  <div className="fee-row"><span>Melava booklet</span><strong>{money(settings.booklet_fee, settings.currency)}</strong></div>
                ) : null}
                <div className="fee-row total"><span>Total payable</span><strong>{money(total, settings.currency)}</strong></div>
                <small>Booklet: {form.booklet_required ? "Requested" : "Not requested"}</small>
              </div>
              <div className="qr-box">
                {settings.qr_image_url ? <img src={settings.qr_image_url} alt="Payment QR code" /> : <div className="qr-empty">QR code unavailable</div>}
                <small>Scan with any UPI app</small>
              </div>
            </div>

            <div className="f screenshot-field">
              <label htmlFor="payment-screenshot">Payment screenshot <b>*</b></label>
              <input id="payment-screenshot" type="file" accept="image/*,.pdf" onChange={handlePaymentFile} />
              <small>Upload a clear screenshot or PDF of your payment, max 5 MB.</small>
              {paymentFile ? (
                <div className="ph">
                  {paymentFile.file.type.startsWith("image/") ? <img src={paymentFile.preview} alt="" /> : <span className="doc-name">{paymentFile.file.name}</span>}
                  <button type="button" className="mini" onClick={() => setPaymentFile(null)}>Remove</button>
                </div>
              ) : null}
              {paymentError ? <div className="al er">{paymentError}</div> : null}
            </div>

            {submitError ? <div className="al er">{submitError}</div> : null}

            <div className="nav">
              <button type="button" className="btn g" disabled={submitting} onClick={() => { setStage("form"); setStep(PREVIEW_STEP); scrollTop(); }}>
                {ICONS.back} Back
              </button>
              <button type="button" className="btn p" disabled={submitting} onClick={submitPayment}>
                {submitting ? "Submitting..." : "Submit Payment"}
              </button>
            </div>
          </section>
        </div>
      </main>
    );
  }

  return (
    <main className="melava-page">
      <div className="wrap">
        <div className="hero">
          <img className="hero-logo" src="/website/Mano_Milan_Final_Website.png" alt="Aaryans Manomilan" />
          <div className="hero-copy">
            <h1>Registration For Melava</h1>
            <p>Fill the form step by step. You can go Back anytime.</p>

          </div>
          {settings.qr_image_url ? <img className="hero-qr" src={settings.qr_image_url} alt="Melava payment QR" /> : null}
        </div>

        <section className="card campaign-card">
          <div className="campaign-art">
            <img src="/img/calender.jpeg" alt="Melava calendar" />
          </div>
          <div className="campaign-details">
            <div className="campaign-title-row">
              <div className="campaign-title">
                <span className="campaign-eyebrow">Upcoming event</span>
                {campaigns.length > 1 ? (
                  <label className="campaign-select-label" htmlFor="melava-campaign">
                    <select id="melava-campaign" value={selectedCampaignId} onChange={(event) => selectCampaign(event.target.value)}>
                      <option value="">Select a Melava</option>
                      {campaigns.map((campaign) => <option key={campaign.id} value={campaign.id}>{campaign.melava_code ? `${campaign.melava_code} - ` : ""}{campaign.title}</option>)}
                    </select>
                  </label>
                ) : (
                  <h2>{settings.batch_label}</h2>
                )}
              </div>
              <span className="campaign-status"><i />Registration Open</span>
            </div>
            {selectedCampaign?.description ? <p className="campaign-description">{selectedCampaign.description}</p> : null}
            <div className="campaign-price">
              <span>Registration <strong>{money(settings.fee, settings.currency)}</strong></span>
              {settings.booklet_available ? (
                <span>Booklet <strong>{money(settings.booklet_fee, settings.currency)}</strong></span>
              ) : (
                <span>Booklet not available</span>
              )}
            </div>
          </div>
        </section>

        <div className="stepper">
          {STEPS.map((item, index) => (
            <button
              type="button"
              key={item.t}
              className={`st ${index === step ? "on" : index < step ? "done" : ""}`}
              onClick={() => jumpToStep(index)}
            >
              <i>{item.i}</i>
              {index + 1}. {item.t}
            </button>
          ))}
          <button
            type="button"
            className={`st ${step === PREVIEW_STEP ? "on" : ""}`}
            onClick={() => jumpToStep(PREVIEW_STEP)}
          >
            <i>{ICONS.eye}</i>
            {TOTAL_STEPS}. Preview
          </button>
        </div>
        <div className="bar"><b style={{ width: `${((step + 1) / TOTAL_STEPS) * 100}%` }} /></div>

        {step === 0 ? (
          <section className="card search">
            <h2>{ICONS.search} Start here: Search your mobile number</h2>
            <p className="sub">
              If you already have a profile on our website, your details will be filled automatically.
              New candidates can search and continue.
            </p>
            <div className="srow" data-field="username">
              <input
                type="tel"
                maxLength={10}
                inputMode="numeric"
                placeholder="Enter 10-digit mobile number"
                aria-label="Mobile number"
                value={form.username}
                onChange={(event) => update("username", event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === "Enter") {
                    event.preventDefault();
                    findCandidate();
                  }
                }}
              />
              <button type="button" className="btn p" onClick={findCandidate} disabled={lookup.status === "loading"}>
                {lookup.status === "loading" ? "Fetching..." : "Fetch / Search Details"}
              </button>
            </div>
            {errors.username ? <div className="al er">{errors.username}</div> : null}
            {lookup.message ? <div className={`al ${lookup.tone || "ok"}`}>{lookup.message}</div> : null}
          </section>
        ) : null}

        {submitError ? <div className="al er">{submitError}</div> : null}

        <form
          onSubmit={(event) => {
            event.preventDefault();
            if (step === PREVIEW_STEP) startPayment();
            else goNext();
          }}
          noValidate
        >
          {step < PREVIEW_STEP ? (
            <section className="card">
              <h2>{STEPS[step].h}</h2>
              <p className="sub">{STEPS[step].s}</p>
              <div className="grid">{STEPS[step].f.map(renderField)}</div>
            </section>
          ) : (
            <section className="card">
              <h2>{ICONS.eye} Preview your details</h2>
              <p className="sub">Please read everything carefully. Press Edit on any section to correct it, then continue to payment.</p>
              <div className="note">{ICONS.warning} After you submit, you will not be able to change this form. Check names, mobile numbers and photos once more.</div>

              {STEPS.map((item, index) => {
                const rows = item.f.filter((field) => !field.sec && fieldVisible(field, form));
                return (
                  <div className="pv" key={item.t}>
                    <header>
                      <span>{item.h}</span>
                      <button type="button" onClick={() => goToStep(index)}>{ICONS.edit} Edit</button>
                    </header>
                    <dl>
                      {rows.map((field) => (
                        <div key={field.n}>
                          <dt>{field.l.replace(/\s*\(.*?\)/g, "")}</dt>
                          <dd className={field.t === "textarea" || field.t === "file" ? "wide" : ""}>{renderPreviewValue(field)}</dd>
                        </div>
                      ))}
                    </dl>
                  </div>
                );
              })}

              {settings.booklet_available ? (
                <div className="opt">
                  <div>
                    <p>{ICONS.book} Do you want Melava Booklet?</p>
                    <small>Booklet fee: {money(settings.booklet_fee, settings.currency)}</small>
                  </div>
                  <div className="pills">
                    <label>
                      <input type="radio" name="booklet" checked={form.booklet_required} onChange={() => update("booklet_required", true)} />
                      <span>Yes</span>
                    </label>
                    <label>
                      <input type="radio" name="booklet" checked={!form.booklet_required} onChange={() => update("booklet_required", false)} />
                      <span>No</span>
                    </label>
                  </div>
                </div>
              ) : null}

              <div className="legal">
                <label>
                  <input type="checkbox" checked={form.disclaimer} onChange={(event) => update("disclaimer", event.target.checked)} />
                  Disclaimer (optional)
                </label>
                <ul>
                  <li>We serve only as a platform to connect prospective brides and grooms. All interactions, verifications and decisions are the sole responsibility of the users.</li>
                  <li>We do not guarantee the accuracy, suitability or authenticity of any profile. Please verify details independently.</li>
                  <li>We are not liable for any loss, damage or disputes arising from use of this platform.</li>
                  <li>Your personal information is kept confidential and not shared without your consent.</li>
                  <li>By using this platform you agree to these terms, which may be updated at any time.</li>
                </ul>
                <label data-field="privacyPolicyCheck">
                  <input type="checkbox" checked={form.privacyPolicyCheck} onChange={(event) => update("privacyPolicyCheck", event.target.checked)} />
                  I accept the Privacy Policy
                </label>
                {errors.privacyPolicyCheck ? <div className="al er">{errors.privacyPolicyCheck}</div> : null}
                <label data-field="termsCheck">
                  <input type="checkbox" checked={form.termsCheck} onChange={(event) => update("termsCheck", event.target.checked)} />
                  I accept the Terms and Conditions
                </label>
                {errors.termsCheck ? <div className="al er">{errors.termsCheck}</div> : null}
                <small>Tick both boxes to continue to payment.</small>
              </div>

              <div className="fee-preview">
                <span>Total payable</span>
                <strong>{money(total, settings.currency)}</strong>
              </div>
            </section>
          )}

          <div className="nav">
            <button type="button" className="btn g" onClick={goBack} disabled={step === 0}>
              {ICONS.back} Back
            </button>
            {step < PREVIEW_STEP ? (
              <button type="button" className="btn p" onClick={goNext}>
                {step === PREVIEW_STEP - 1 ? "Preview" : "Next"} {ICONS.next}
              </button>
            ) : (
              <button type="submit" className="btn p" disabled={submitting || !form.privacyPolicyCheck || !form.termsCheck}>
                {submitting ? "Saving..." : "Continue to Payment"} {ICONS.next}
              </button>
            )}
          </div>
        </form>
      </div>
    </main>
  );
}