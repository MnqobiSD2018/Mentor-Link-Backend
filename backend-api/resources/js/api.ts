// api.ts
export const API_BASE_URL = "/api";

export async function fetchAPI(endpoint: string, options: RequestInit = {}) {
  const token = localStorage.getItem("token");
  
  const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    ...options.headers,
  } as HeadersInit;

  if (token) {
    (headers as any)["Authorization"] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers,
  });

  const contentType = response.headers.get("content-type");
  let data;
  if (contentType && contentType.indexOf("application/json") !== -1) {
    data = await response.json();
  } else {
    // If response is not JSON, we probably have an error page or text
    const text = await response.text();
    // Start with a generic error, but attach the text content for debugging if needed
    data = { message: `Non-JSON response: ${response.status} ${response.statusText}` };
    console.error("API Error (Non-JSON response):", text);
  }

  if (!response.ok) {
    throw new Error(data.message || `API Error: ${response.statusText}`);
  }

  return data;
}

export const auth = {
  login: async (credentials: any) => {
    return fetchAPI("/login", {
      method: "POST",
      body: JSON.stringify(credentials),
    });
  },
  
  register: async (userData: any) => {
    return fetchAPI("/register", {
      method: "POST",
      body: JSON.stringify(userData),
    });
  },

  logout: async () => {
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    return fetchAPI("/logout", {
      method: "POST",
    });
  },
};

export const dashboard = {
  getMenteeData: async () => {
    return fetchAPI("/dashboard/mentee");
  },
  getMentorData: async () => {
    return fetchAPI("/dashboard/mentor");
  },
  getAdminData: async () => {
    return fetchAPI("/dashboard/admin");
  },
};

export const mentors = {
  list: async () => {
    return fetchAPI("/mentors");
  },
  get: async (id: number | string) => {
    return fetchAPI(`/mentors/${id}`);
  },
};

export const sessions = {
  list: async () => {
    // Always try real API first
    try {
      const res = await fetchAPI("/sessions");
      // Handle various response formats
      if (Array.isArray(res)) return res;
      if (res.data && Array.isArray(res.data)) return res.data;
      if (res.sessions && Array.isArray(res.sessions)) return res.sessions;
      return res;
    } catch (e) {
      console.error("Failed to fetch sessions from API:", e);
      throw e; // Re-throw so UI can handle the error
    }
  },
  create: async (data: any) => {
    // Always use real API
    const response = await fetchAPI("/sessions", {
      method: "POST",
      body: JSON.stringify(data),
    });
    return response.data || response;
  },
  updateStatus: async (id: number, status: string) => {
    const response = await fetchAPI(`/sessions/${id}`, {
      method: "PUT",
      body: JSON.stringify({ status }),
    });
    return response.data || response;
  }
};

export const messages = {
  getConversations: async () => {
    return fetchAPI("/messages/conversations");
  },
  getMessages: async (conversationId: number) => {
    return fetchAPI(`/messages/${conversationId}`);
  },
  createConversation: async (participantId: number | string) => {
      // Assuming a standard convention where we post to /conversations with participant_id
      // If this endpoint doesn't exist, we might need to adjust based on backend docs
      return fetchAPI("/conversations", {
          method: "POST",
          body: JSON.stringify({ participant_id: participantId }),
      });
  },
  sendMessage: async (conversationId: number, content: string) => {
    return fetchAPI(`/messages/${conversationId}`, {
      method: "POST",
      body: JSON.stringify({ content }),
    });
  }
};

export const payments = {
  list: async () => {
    // Note: GET /api/payments is not in the initial route list, 
    // but is standard for a payments page.
    return fetchAPI("/payments");
  },
  create: async (data: { amount: number; description: string; method: string }) => {
    return fetchAPI("/payments", {
      method: "POST",
      body: JSON.stringify(data),
    });
  }
};

export const profile = {
  get: async () => {
    return fetchAPI("/user"); // Standard Sanctum user endpoint
  },
  update: async (data: any) => {
    return fetchAPI("/profile", {
      method: "PUT",
      body: JSON.stringify(data),
    });
  },
  updatePassword: async (data: any) => {
    return fetchAPI("/password", {
      method: "PUT",
      body: JSON.stringify(data),
    });
  },
  uploadAvatar: async (file: File) => {
    const token = localStorage.getItem("token");
    const formData = new FormData();
    formData.append("avatar", file);

    const response = await fetch(`${API_BASE_URL}/profile/avatar`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: formData,
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || "Failed to upload avatar");
    }

    return data;
  },
  removeAvatar: async () => {
    return fetchAPI("/profile/avatar", {
      method: "DELETE",
    });
  }
};

export const admin = {
  getUsers: async () => {
    return fetchAPI("/admin/users");
  },
  getUser: async (id: number) => {
    return fetchAPI(`/admin/users/${id}`);
  },
  updateUserStatus: async (id: number, status: string) => {
    return fetchAPI(`/admin/users/${id}/status`, {
      method: "PUT",
      body: JSON.stringify({ status }),
    });
  },
  deleteUser: async (id: number) => {
    return fetchAPI(`/admin/users/${id}`, {
      method: "DELETE",
    });
  }
};

export const verification = {
  list: async () => {
    return fetchAPI("/admin/verifications");
  },
  approve: async (id: number) => {
    return fetchAPI(`/admin/verifications/${id}/approve`, {
      method: "POST",
    });
  },
  reject: async (id: number) => {
    return fetchAPI(`/admin/verifications/${id}/reject`, {
      method: "POST",
    });
  },
};

export const adminPayments = {
  list: async () => {
    return fetchAPI("/admin/payments");
  },
  stats: async () => {
    return fetchAPI("/admin/payments/stats");
  }
};

export const adminAnalytics = {
  get: async () => {
    return fetchAPI("/admin/analytics");
  }
};

export const adminSecurity = {
  getStats: async () => {
    return fetchAPI("/admin/security/stats");
  },
  getLogs: async () => {
    return fetchAPI("/admin/security/logs");
  },
  searchLogs: async (query: string) => {
     return fetchAPI(`/admin/security/logs?query=${encodeURIComponent(query)}`);
  },
  updateSetting: async (key: string, value: boolean) => {
    return fetchAPI("/admin/security/settings", {
      method: "PUT",
      body: JSON.stringify({ key, value }),
    });
  }
};

export const availability = {
  getSlots: async () => {
    return fetchAPI("/availability/slots");
  },
  addSlot: async (data: any) => {
    return fetchAPI("/availability/slots", {
      method: "POST",
      body: JSON.stringify(data),
    });
  },
  deleteSlot: async (id: number) => {
    return fetchAPI(`/availability/slots/${id}`, {
      method: "DELETE",
    });
  },
  getBlockedDates: async () => {
    return fetchAPI("/availability/blocked");
  },
  addBlockedDate: async (data: any) => {
    return fetchAPI("/availability/blocked", {
      method: "POST",
      body: JSON.stringify(data),
    });
  },
  deleteBlockedDate: async (id: number) => {
    return fetchAPI(`/availability/blocked/${id}`, {
      method: "DELETE",
    });
  }
};

export const reviews = {
  list: async () => {
    return fetchAPI("/reviews/mentor"); // Endpoint to get reviews FOR the authenticated mentor
  },
};

export const earnings = {
  get: async () => {
     return fetchAPI("/earnings/mentor");
  },
  withdraw: async () => {
     return fetchAPI("/earnings/withdraw", { method: "POST" });
  }
};
