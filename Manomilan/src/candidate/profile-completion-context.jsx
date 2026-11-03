import { createContext, useContext } from "react";

export const ProfileCompletionContext = createContext({ status: "idle", completion: 0 });

export const useProfileCompletion = () => useContext(ProfileCompletionContext);
