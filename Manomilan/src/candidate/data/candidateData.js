export const candidate = {
  name: "Priya Shah",
  id: "MM-10234",
  plan: "Premium",
  planExpiry: "31 Mar 2027",
  profileCompletion: 78,
};

export const stats = {
  profileViews: 128,
  interestsReceived: 14,
  interestsSent: 9,
  shortlisted: 6,
};

export const menuItems = [
  { label: "Dashboard", path: "/candidate" },
  { label: "My Profile", path: "/candidate/profile" },
  { label: "Find Matches", path: "/candidate/matches" },
  { label: "Interests Received", path: "/candidate/interests-received" },
  { label: "Interests Sent", path: "/candidate/interests-sent" },
  { label: "Shortlisted", path: "/candidate/shortlisted" },
  { label: "Membership Plan", path: "/candidate/membership" },
];

export const profileChecklist = [
  { label: "Basic details", done: true },
  { label: "Education & occupation", done: true },
  { label: "Family details", done: true },
  { label: "Upload photos", done: false },
  { label: "Partner preferences", done: false },
];

export const recentInterests = [
  {
    id: 1,
    name: "Rohan Jain",
    age: 29,
    city: "Pune",
    time: "2 hours ago",
    status: "Pending",
  },
  {
    id: 2,
    name: "Amit Shah",
    age: 31,
    city: "Mumbai",
    time: "Yesterday",
    status: "Accepted",
  },
  {
    id: 3,
    name: "Nikhil Mehta",
    age: 28,
    city: "Nashik",
    time: "2 days ago",
    status: "Pending",
  },
  {
    id: 4,
    name: "Sagar Oswal",
    age: 30,
    city: "Kolhapur",
    time: "4 days ago",
    status: "Declined",
  },
];

export const suggestedMatches = [
  {
    id: 1,
    name: "Rohan Jain",
    age: 29,
    city: "Pune",
    occupation: "Software Engineer",
    education: "B.E. Computer",
  },
  {
    id: 2,
    name: "Karan Doshi",
    age: 30,
    city: "Mumbai",
    occupation: "Chartered Accountant",
    education: "CA",
  },
  {
    id: 3,
    name: "Vishal Patil",
    age: 28,
    city: "Satara",
    occupation: "Business Owner",
    education: "B.Com",
  },
  {
    id: 4,
    name: "Mayur Gandhi",
    age: 32,
    city: "Pune",
    occupation: "Doctor",
    education: "MBBS, MD",
  },
];
