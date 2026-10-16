<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import {
    Users,
    Eye,
    CheckCircle,
    XCircle,
    UserPlus,
    Search,
    Filter,
    Calendar,
    Mail,
    Phone,
    Briefcase,
    MapPin,
    FileCheck,
    Upload,
    Trash2,
    ShieldCheck,
    Save,
    X,
    Plus,
    ChevronDown,
    ChevronUp,
    BookPlus,
    Edit,
} from "lucide-vue-next";

const props = defineProps({
    applicants: {
        type: Array,
        default: () => [],
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const user = page.props.auth.user;

// ----- FIXED canEdit: uses page_permissions and role/position fallback -----
const canEdit = computed(() => {
    // 1. Check explicit edit permission on the 'application' page
    const perms = user?.page_permissions || [];
    const hasPerm = perms.some(
        p => p.module === 'HRM' && p.page === 'application' && p.permission_level === 'edit'
    );
    if (hasPerm) return true;

    // 2. Fallback: HRM manager has full edit rights
    if (user?.role === 'HRM' && user?.position === 'manager') return true;

    // 3. CEO also has edit rights (already covered by CEO bypass in middleware)
    if (user?.role === 'CEO') return true;

    return false;
});

// Toast notification
const showToast = ref(false);
const toastMessage = ref("");
const toastType = ref("success");

const triggerToast = (msg, type = "success") => {
    toastMessage.value = msg;
    toastType.value = type;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 4000);
};

// Flash messages from server
if (page.props.flash?.message) {
    triggerToast(page.props.flash.message);
}

// Filters
const searchQuery = ref("");
const statusFilter = ref("pending");

const filteredApplicants = computed(() => {
    let list = props.applicants.filter(
        (a) => a.status?.toLowerCase() === "pending",
    );
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (a) =>
                a.name.toLowerCase().includes(q) ||
                a.email.toLowerCase().includes(q) ||
                a.position_applied.toLowerCase().includes(q),
        );
    }
    return list;
});

// Modal states
const isViewModalOpen = ref(false);
const isAcceptModalOpen = ref(false);
const isRejectModalOpen = ref(false);
const isAddModalOpen = ref(false);
const selectedApplicant = ref(null);
const selectedModule = ref("");
const rejectionReason = ref("");

// --- NEW POSITIONS MODAL STATE ---
const isPositionModalOpen = ref(false);
const isLoadingPositions = ref(false);
const isSubmittingPosition = ref(false);
const newPositionTitle = ref("");
const positions = ref([]);
// Edit position modal
const isEditPositionModalOpen = ref(false);
const editingPosition = ref(null);
const editPositionTitle = ref("");
// ---------------------------------

// Module options for acceptance (only core modules)
const modules = [
    { value: "HRM", label: "Human Resource" },
    { value: "CRM", label: "Customer Relationship" },
    { value: "MAN", label: "Manufacturing" },
    { value: "LOG", label: "Logistics" },
];

// Image viewer modal
const isImageViewerOpen = ref(false);
const currentImageUrl = ref("");

const openImageViewer = (url) => {
    if (!url) return;
    currentImageUrl.value = url;
    isImageViewerOpen.value = true;
};

const isOldEnough = (dob) => {
    if (!dob) return false;
    const age = Math.floor((new Date() - new Date(dob)) / 31557600000);
    return age >= 18;
};

// Form for adding new applicant
const addForm = useForm({
    first_name: "",
    middle_name: "",
    last_name: "",
    email: "",
    phone_country: "+63",
    phone_raw: "",
    phone_number: "",
    street_address: "",
    city: "",
    state_province: "",
    postal_zip_code: "",
    notice_period: "immediate",
    position_applied: "",
    status: "pending",
    sss_file: null,
    philhealth_file: null,
    pagibig_file: null,
    image: null,
    date_of_birth: "",
    place_of_birth: "",
    citizenship: "",
    weight: "",
    height: "",
    civil_status: "",
    sex: "",
    religion: "",
    contact_number: "",
    sss_number: "",
    philhealth_number: "",
    pagibig_number: "",
    spouse_name: "",
    spouse_occupation: "",
    spouse_address: "",
    mother_name: "",
    mother_address: "",
    father_name: "",
    father_address: "",
    languages: "",
    emergency_name: "",
    emergency_relationship: "",
    emergency_phone: "",
    emergency_address: "",
    elementary_school: "",
    elementary_year: "",
    high_school: "",
    high_year: "",
    college: "",
    college_year: "",
    vocational: "",
    vocational_year: "",
    special_skills: "",
    has_employment_record: false,
    machine_operation: "",
    referred_by: "",
    referred_by_address: "",
    previous_employment_company: "",
    previous_employment_when: "",
    previous_employment_position: "",
    previous_employment_department: "",
    related_employees: "",
    children: [],
    employment_records: [],
    number_of_children: 0,
});

// Dynamic arrays for children and employment records in add modal
const childrenList = ref([]);
const employmentList = ref([]);
const showEmploymentSection = ref(false);

const addChild = () => childrenList.value.push({ name: "", dob: "" });
const removeChild = (idx) => childrenList.value.splice(idx, 1);

const addEmployment = () =>
    employmentList.value.push({
        company: "",
        years: "",
        salary: "",
        position: "",
        reason: "",
    });
const removeEmployment = (idx) => employmentList.value.splice(idx, 1);

watch(showEmploymentSection, (val) => {
    addForm.has_employment_record = val;
});

// Helper functions for display
const getInitials = (name) =>
    name
        ? name
              .split(" ")
              .map((n) => n[0])
              .join("")
              .toUpperCase()
              .slice(0, 2)
        : "?";
const formatDate = (date) =>
    date ? new Date(date).toLocaleDateString() : "N/A";
const getStatusBadgeClass = (status) => {
    const s = status?.toLowerCase();
    if (s === "pending")
        return "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400";
    if (s === "interview")
        return "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400";
    if (s === "rejected")
        return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400";
    return "bg-slate-100 text-slate-700";
};

// Open modals
const openViewModal = (applicant) => {
    selectedApplicant.value = applicant;
    isViewModalOpen.value = true;
};

const openAcceptModal = (applicant) => {
    if (!canEdit.value) {
        triggerToast("You do not have permission to accept applications.", 'error');
        return;
    }
    selectedApplicant.value = applicant;
    selectedModule.value = "";
    isAcceptModalOpen.value = true;
};

const openRejectModal = (applicant) => {
    if (!canEdit.value) {
        triggerToast("You do not have permission to reject applications.", 'error');
        return;
    }
    selectedApplicant.value = applicant;
    rejectionReason.value = "";
    isRejectModalOpen.value = true;
};

const openAddModal = () => {
    if (!canEdit.value) {
        triggerToast("You do not have permission to add applicants.", 'error');
        return;
    }
    addForm.reset();
    childrenList.value = [];
    employmentList.value = [];
    showEmploymentSection.value = false;
    isAddModalOpen.value = true;
};

// --- NEW POSITIONS MODAL METHODS ---
const openAddPositionModal = async () => {
    if (!canEdit.value) {
        triggerToast("You do not have permission to manage positions.", 'error');
        return;
    }
    isPositionModalOpen.value = true;
    await fetchPositions();
};

const closePositionModal = () => {
    isPositionModalOpen.value = false;
    newPositionTitle.value = "";
};

// 1. Fetch data (GET requests don't need CSRF tokens)
const fetchPositions = async () => {
    isLoadingPositions.value = true;
    try {
        const response = await fetch('/dashboard/hrm/positions');
        if (!response.ok) throw new Error("Network response was not ok");
        const data = await response.json();
        positions.value = data;
    } catch (error) {
        console.error("Error fetching positions:", error);
        triggerToast("Failed to load positions.");
    } finally {
        isLoadingPositions.value = false;
    }
};

const submitPosition = () => {
    if (!newPositionTitle.value.trim()) return;

    router.post(route('hrm.positions.store'), {
        position: newPositionTitle.value
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            triggerToast("Position added successfully.");
            newPositionTitle.value = '';
            fetchPositions();
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Submission failed. Please check your inputs.';
            triggerToast(errorMsg);
        }
    });
};

// Edit position
const openEditPositionModal = (position) => {
    editingPosition.value = position;
    editPositionTitle.value = position.position;
    isEditPositionModalOpen.value = true;
};

const updatePosition = () => {
    if (!editPositionTitle.value.trim()) return;

    router.patch(route('hrm.positions.update', editingPosition.value.id), {
        position: editPositionTitle.value
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            triggerToast("Position updated successfully.");
            isEditPositionModalOpen.value = false;
            editingPosition.value = null;
            editPositionTitle.value = "";
            fetchPositions();
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Update failed.';
            triggerToast(errorMsg);
        }
    });
};

// Toggle status
const togglePositionStatus = async (position) => {
    const newStatus = position.status === "active" ? "inactive" : "active";

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const response = await fetch(`/dashboard/hrm/positions/${position.id}/toggle-status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ status: newStatus }),
        });

        if (response.ok) {
            const data = await response.json();
            position.status = data.position.status;
            triggerToast(`Position marked as ${position.status}.`);
        } else {
            console.error("Failed to update. Check your CSRF token or route.");
            triggerToast("Failed to update status.");
        }
    } catch (error) {
        console.error("Error updating status:", error);
        triggerToast("Failed to update status.");
    }
};

const toggleStatus = async (id) => {
    const position = positions.value.find((pos) => pos.id === id);
    if (!position) return;
    await togglePositionStatus(position);
};

// Delete Position
const deletePosition = async (id) => {
    if (!confirm("Are you sure you want to delete this position?")) return;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const response = await fetch(`/dashboard/hrm/positions/${id}`, {
            method: "DELETE",
            headers: {
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrfToken
            },
        });

        if (!response.ok) throw new Error("Failed to delete position");

        positions.value = positions.value.filter((pos) => pos.id !== id);
        triggerToast("Position deleted.");
    } catch (error) {
        console.error("Failed to delete position:", error);
        triggerToast("Failed to delete position.");
    }
};

// Active positions for dropdown
const activePositions = computed(() => {
    return positions.value.filter(pos => pos.status === 'active');
});

// -------------------------------------------------

// Form submissions
const acceptApplicant = () => {
    if (!selectedModule.value) {
        triggerToast("Please select a module for interview.");
        return;
    }
    router.post(
        route("hrm.applications.accept", selectedApplicant.value.id),
        {
            module: selectedModule.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast(
                    `Applicant assigned to ${selectedModule.value} for interview.`,
                );
                isAcceptModalOpen.value = false;
                selectedApplicant.value = null;
            },
            onError: (errors) => {
                triggerToast(Object.values(errors)[0] || "Acceptance failed.");
            },
        },
    );
};

const rejectApplicant = () => {
    if (!rejectionReason.value.trim()) {
        triggerToast("Please provide a reason for rejection.");
        return;
    }
    router.post(
        route("hrm.applications.reject", selectedApplicant.value.id),
        {
            reason: rejectionReason.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast(
                    `Applicant ${selectedApplicant.value.name} rejected.`,
                );
                isRejectModalOpen.value = false;
                selectedApplicant.value = null;
            },
            onError: (errors) => {
                triggerToast(Object.values(errors)[0] || "Rejection failed.");
            },
        },
    );
};

// Real-time Validation and Sanitization
const inputWarnings = ref({});
let warningTimeouts = {};

const triggerWarning = (field, message) => {
    inputWarnings.value[field] = message;
    if (warningTimeouts[field]) clearTimeout(warningTimeouts[field]);
    warningTimeouts[field] = setTimeout(() => {
        inputWarnings.value[field] = "";
    }, 3000);
};

const sanitizeWithFallback = (original, pattern, field) => {
    const filtered = original.replace(pattern, "");
    if (filtered === "" && original !== "") {
        triggerWarning(
            field,
            "Invalid characters detected – please use only allowed characters.",
        );
        return original;
    }
    return filtered;
};

const blockNumbersAndSpecial = (e, field) => {
    if (e.key.length === 1 && !/^[a-zA-Z\sñÑ-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning(
            field,
            "Numbers and special characters are not allowed.",
        );
    }
};

const blockSpecialForAddress = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9\sñÑ.,#-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning(
            "street_address",
            "Invalid character. Use alphanumeric and basic punctuation.",
        );
    }
};

const blockSpecialForEmail = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9@.\-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning(
            "email",
            "Invalid character. Only letters, numbers, @, ., and - are allowed.",
        );
    }
};

const sanitizeName = (val, field) => {
    const filtered = sanitizeWithFallback(val, /[^a-zA-Z\sñÑ-]/g, field);
    if (val !== filtered) {
        addForm[field] = filtered;
        triggerWarning(field, "Invalid characters removed.");
    }
};

watch(
    () => addForm.first_name,
    (val) => sanitizeName(val, "first_name"),
);
watch(
    () => addForm.middle_name,
    (val) => sanitizeName(val, "middle_name"),
);
watch(
    () => addForm.last_name,
    (val) => sanitizeName(val, "last_name"),
);
watch(
    () => addForm.city,
    (val) => sanitizeName(val, "city"),
);
watch(
    () => addForm.state_province,
    (val) => sanitizeName(val, "state_province"),
);

watch(
    () => addForm.street_address,
    (val) => {
        const filtered = sanitizeWithFallback(
            val,
            /[^a-zA-Z0-9\sñÑ.,#-]/g,
            "street_address",
        );
        if (val !== filtered) addForm.street_address = filtered;
    },
);

watch(
    () => addForm.email,
    (val) => {
        const filtered = val.replace(/[^a-zA-Z0-9@.\-]/g, "");
        if (val !== filtered) {
            addForm.email = filtered;
            triggerWarning("email", "Invalid characters removed.");
        }
    },
);

watch(
    () => addForm.phone_raw,
    (val) => {
        const filtered = val.replace(/\D/g, "").substring(0, 12);
        if (val !== filtered) {
            addForm.phone_raw = filtered;
            triggerWarning("phone_raw", "Numbers only.");
        }
    },
);

watch(
    () => addForm.postal_zip_code,
    (val) => {
        const filtered = val.replace(/\D/g, "").substring(0, 4);
        if (val !== filtered) {
            addForm.postal_zip_code = filtered;
            triggerWarning("postal_zip_code", "Only digits allowed.");
        }
    },
);

const handleImageUpload = (e) => {
    const file = e.target.files[0];
    if (file) addForm.image = file;
};

const handleFileUpload = (e, field) => {
    const file = e.target.files[0];
    if (file) addForm[field] = file;
};

const removeFile = (field) => {
    addForm[field] = null;
};

// Submit Form
const addApplicant = () => {
    if (
        !/^[a-zA-Z\sñÑ-]+$/.test(addForm.first_name) ||
        !/^[a-zA-Z\sñÑ-]+$/.test(addForm.last_name)
    ) {
        triggerToast("First Name and Last Name must only contain letters.", 'error');
        return;
    }

     // Weight Validation Rules bagong add yah
            const weightVal = parseFloat(addForm.weight);
    
            if (addForm. weight && (weightVal < 30 || weightVal > 200)) {
    
              triggerToast('Please enter a valid weight (30kg - 200kg).', 'error');
                return;
            }
    
            // Height Validation Rules (Optional but recommended)
            const heightVal = parseFloat(addForm.height);
            if (addForm.height && (heightVal < 100 || heightVal > 250)) {
             triggerToast('Please enter a valid height (100cm - 250cm).', 'error');
                return;
            }
    
        if (!isOldEnough(addForm.date_of_birth)) {
             triggerToast(' Applicants must be 18 years or older.', 'error');
            return; 
        }
    

    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!emailRegex.test(addForm.email)) {
        triggerToast("Please enter a valid email address.", 'error');
        return;
    }

    if (
        addForm.phone_raw.length !== 12 ||
        !/^\d{12}$/.test(addForm.phone_raw)
    ) {
        triggerToast("Phone number must be exactly 12 digits.", 'error');
        return;
    }

    if (!addForm.position_applied) {
        triggerToast("Please select the position applied for.", 'error');
        return;
    }

    const street = addForm.street_address?.trim() || "";
    const city = addForm.city?.trim() || "";
    const state = addForm.state_province?.trim() || "";
    if (!street || !city || !state) {
        let missing = [];
        if (!street) missing.push("Street Address");
        if (!city) missing.push("City");
        if (!state) missing.push("State/Province");
        triggerToast(
            `Complete residential details are required. Missing: ${missing.join(", ")}`,
        );
        return;
    }

    if (addForm.postal_zip_code.length !== 4) {
        triggerToast("Postal/Zip code must be exactly 4 digits.");
        return;
    }

    addForm.children = childrenList.value;
    addForm.employment_records = employmentList.value;
    addForm.has_employment_record = showEmploymentSection.value;
    addForm.number_of_children = childrenList.value.length;

    if (addForm.weight) addForm.weight = parseFloat(addForm.weight);
    if (addForm.height) addForm.height = parseFloat(addForm.height);

    addForm.phone_number = `${addForm.phone_country}${addForm.phone_raw}`;

    addForm.post(route("hrm.applications.store"), {
        forceFormData: true,
        onSuccess: () => {
            triggerToast("Applicant added successfully.");
            isAddModalOpen.value = false;
        },
        onError: (errors) => {
            const msg =
                Object.values(errors)[0] ||
                "Failed to add applicant. Please check inputs.";
            triggerToast(msg);
        },
    });
};

const enforceNumbersOnly = (event) => {
    const cleanedValue = event.target.value.replace(/[^0-9]/g, "");
    event.target.value = cleanedValue;
    addForm.phone_raw = cleanedValue;
};

const blockNonNumbers = (event) => {
    if (!/[0-9]/.test(event.key)) {
        event.preventDefault();
    }
};

const blockInvalidSalaryKeys = (event) => {
    const blockedKeys = ["-", "+", "e", "E"];
    if (blockedKeys.includes(event.key)) {
        event.preventDefault();
    }
};

// Fetch data when component loads
onMounted(() => {
    fetchPositions();
});

</script>

<template>
    <Head title="Application Management" />

    <AuthenticatedLayout>
        <Transition name="toast">
            <div
                v-if="showToast"
            :class="[
            toastType === 'error' 
                ? 'bg-red-600 border-red-500 text-white' 
                : 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 border-white/10',
                'fixed top-6 right-6 z-[100] flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl border'

               ]"
                >
                <XCircle v-if="toastType === 'error'" class="h-5 w-5 text-white" />
        
                    <CheckCircle 
                        v-else 
                        class="h-5 w-5 text-emerald-400 dark:text-emerald-600" 
                    />
                <p class="text-sm font-bold uppercase tracking-tight">
                    {{ toastMessage }}
                </p>
            </div>
        </Transition>

        <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 sm:space-y-8">
            <div
                class="flex flex-col md:flex-row md:items-center justify-between gap-4"
            >
                <div>
                    <h1
                        class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight"
                    >
                        Application
                        <span class="text-blue-600">Management</span>
                    </h1>
                    <p
                        class="text-slate-500 dark:text-slate-400 text-sm font-medium"
                    >
                        Review and process new job applications.
                    </p>
                </div>

                <div
                    class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto mt-4 md:mt-0"
                >
                    <button
                        v-if="canEdit"
                        @click="openAddPositionModal"
                        class="w-full sm:w-auto flex justify-center items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-bold text-sm transition-all shadow-lg shadow-blue-500/20 active:scale-95"
                    >
                        <BookPlus class="h-4 w-4" /> Add position
                    </button>

                    <button
                        v-if="canEdit"
                        @click="openAddModal"
                        class="w-full sm:w-auto flex justify-center items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-bold text-sm transition-all shadow-lg shadow-blue-500/20 active:scale-95"
                    >
                        <UserPlus class="h-4 w-4" /> Add New Applicant
                    </button>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4">
                <div class="relative flex-1">
                    <Search
                        class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"
                    />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by name, email, or position..."
                        class="w-full pl-11 pr-4 py-3 rounded-2xl bg-white dark:bg-slate-800 border-none ring-1 ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-600 text-sm"
                    />
                </div>
                <div class="flex gap-2">
                    <button
                        class="flex items-center gap-2 px-5 py-3 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-600 hover:bg-slate-50 transition-all"
                    >
                        <Filter class="h-4 w-4" /> Filters
                    </button>
                </div>
            </div>

            <div
                class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/60 dark:bg-slate-900/40">
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest"
                                >
                                    Applicant
                                </th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest"
                                >
                                    Position
                                </th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest"
                                >
                                    Date Applied
                                </th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest"
                                >
                                    Status
                                </th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-700"
                        >
                            <tr v-if="filteredApplicants.length === 0">
                                <td
                                    colspan="5"
                                    class="px-6 py-12 text-center text-sm text-slate-500"
                                >
                                    No pending applications found.
                                </td>
                            </tr>
                            <tr
                                v-for="applicant in filteredApplicants"
                                :key="applicant.id"
                                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors group"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 font-bold text-xs overflow-hidden"
                                        >
                                            <img
                                                v-if="applicant.image_url"
                                                :src="applicant.image_url"
                                                alt="Profile"
                                                class="h-full w-full object-cover"
                                            />
                                            <span v-else>
                                                {{ getInitials(applicant.name) }}
                                            </span>
                                        </div>
                                        <div>
                                            <p
                                                class="text-sm font-bold text-slate-900 dark:text-white"
                                            >
                                                {{ applicant.name }}
                                            </p>
                                            <p class="text-xs text-slate-400">
                                                {{ applicant.email }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm font-semibold text-slate-600 dark:text-slate-300"
                                >
                                    {{ applicant.position_applied }}
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-500">
                                    {{ formatDate(applicant.created_at) }}
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="[
                                            getStatusBadgeClass(
                                                applicant.status,
                                            ),
                                            'px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider',
                                        ]"
                                    >
                                        {{ applicant.status || "Pending" }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <button
                                            @click="openViewModal(applicant)"
                                            class="p-2 text-slate-400 hover:text-blue-600 rounded-lg transition-all"
                                            title="View Details"
                                        >
                                            <Eye class="h-5 w-5" />
                                        </button>
                                        <button
                                            v-if="canEdit"
                                            @click="openAcceptModal(applicant)"
                                            class="p-2 text-slate-400 hover:text-emerald-600 rounded-lg transition-all"
                                            title="Accept"
                                        >
                                            <CheckCircle class="h-5 w-5" />
                                        </button>
                                        <button
                                            v-if="canEdit"
                                            @click="openRejectModal(applicant)"
                                            class="p-2 text-slate-400 hover:text-red-600 rounded-lg transition-all"
                                            title="Reject"
                                        >
                                            <XCircle class="h-5 w-5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- View Applicant Modal -->
        <div
            v-if="isViewModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md"
            @click.self="isViewModalOpen = false"
        >
            <div
                class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col"
            >
                <div
                    class="px-8 py-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center sticky top-0 bg-white dark:bg-slate-800"
                >
                    <h2
                        class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tight"
                    >
                        Applicant Details
                    </h2>
                    <button
                        @click="isViewModalOpen = false"
                        class="p-2 hover:bg-slate-100 rounded-full transition-colors"
                    >
                        <X class="h-5 w-5 text-slate-400" />
                    </button>
                </div>
                <div class="overflow-y-auto p-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3
                                class="text-xs font-black text-blue-600 uppercase tracking-widest mb-3"
                            >
                                Personal Information
                            </h3>
                            <div
                                class="bg-slate-50 dark:bg-slate-900/40 p-6 rounded-2xl space-y-4"
                            >
                                <div class="flex items-center gap-4 pb-2">
                                    <div
                                        class="h-32 w-32 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700 flex items-center justify-center shrink-0 border-2 border-white dark:border-slate-600 shadow-sm"
                                    >
                                        <img
                                            v-if="selectedApplicant?.image_url"
                                            :src="selectedApplicant.image_url"
                                            alt="Profile"
                                            class="h-full w-full object-cover"
                                        />
                                        <span
                                            v-else
                                            class="text-slate-500 font-bold text-xl"
                                        >
                                            {{
                                                getInitials(
                                                    selectedApplicant?.name,
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <div>
                                        <p
                                            class="text-[10px] font-bold text-slate-400"
                                        >
                                            Full Name
                                        </p>
                                        <p
                                            class="text-lg font-black text-slate-900 dark:text-white"
                                        >
                                            {{
                                                selectedApplicant?.name || "N/A"
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p
                                            class="text-[10px] font-bold text-slate-400"
                                        >
                                            Email
                                        </p>
                                        <p class="text-xs">
                                            {{
                                                selectedApplicant?.email ||
                                                "N/A"
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-[10px] font-bold text-slate-400"
                                        >
                                            Phone
                                        </p>
                                        <p class="text-xs">
                                            {{
                                                selectedApplicant?.phone_number ||
                                                "N/A"
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Address
                                    </p>
                                    <p class="text-xs">
                                        {{
                                            selectedApplicant?.street_address ||
                                            "N/A"
                                        }},
                                        {{ selectedApplicant?.city || "N/A" }},
                                        {{
                                            selectedApplicant?.state_province ||
                                            "N/A"
                                        }}
                                        {{
                                            selectedApplicant?.postal_zip_code ||
                                            "N/A"
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3
                                class="text-xs font-black text-emerald-600 uppercase tracking-widest mb-3"
                            >
                                Application Details
                            </h3>
                            <div
                                class="bg-slate-50 dark:bg-slate-900/40 p-6 rounded-2xl space-y-4"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Position Applied
                                    </p>
                                    <p class="text-sm font-bold">
                                        {{
                                            selectedApplicant?.position_applied ||
                                            "N/A"
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Notice Period
                                    </p>
                                    <p
                                        class="text-sm uppercase font-bold text-amber-600"
                                    >
                                        {{
                                            selectedApplicant?.notice_period?.replace(
                                                "_",
                                                " ",
                                            ) || "N/A"
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="selectedApplicant?.date_of_birth"
                        class="grid grid-cols-1 md:grid-cols-2 gap-6"
                    >
                        <div>
                            <h3
                                class="text-xs font-black text-blue-600 uppercase tracking-widest mb-3"
                            >
                                Birth & Personal
                            </h3>
                            <div
                                class="bg-slate-50 dark:bg-slate-900/40 p-6 rounded-2xl space-y-4"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Date of Birth
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            formatDate(
                                                selectedApplicant.date_of_birth,
                                            ) || "N/A"
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Place of Birth
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            selectedApplicant.place_of_birth ||
                                            "N/A"
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Civil Status
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            selectedApplicant.civil_status ||
                                            "N/A"
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Religion
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            selectedApplicant.religion || "N/A"
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h3
                                class="text-xs font-black text-emerald-600 uppercase tracking-widest mb-3"
                            >
                                Government IDs
                            </h3>
                            <div
                                class="bg-slate-50 dark:bg-slate-900/40 p-6 rounded-2xl space-y-4"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        SSS Number
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            selectedApplicant.sss_number ||
                                            "N/A"
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        PhilHealth Number
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            selectedApplicant.philhealth_number ||
                                            "N/A"
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Pag-IBIG Number
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            selectedApplicant.pagibig_number ||
                                            "N/A"
                                        }}
                                    </p>
                                </div>
                                <div class="grid grid-cols-3 gap-2 mt-2">
                                    <div v-if="selectedApplicant.sss_file_url">
                                        <button
                                            @click="openImageViewer(selectedApplicant.sss_file_url)"
                                            class="text-blue-600 text-xs underline hover:no-underline"
                                        >
                                            SSS ID
                                        </button>
                                    </div>
                                    <div
                                        v-if="
                                            selectedApplicant.philhealth_file_url
                                        "
                                    >
                                        <button
                                            @click="openImageViewer(selectedApplicant.philhealth_file_url)"
                                            class="text-blue-600 text-xs underline hover:no-underline"
                                        >
                                            PhilHealth ID
                                        </button>
                                    </div>
                                    <div
                                        v-if="
                                            selectedApplicant.pagibig_file_url
                                        "
                                    >
                                        <button
                                            @click="openImageViewer(selectedApplicant.pagibig_file_url)"
                                            class="text-blue-600 text-xs underline hover:no-underline"
                                        >
                                            Pag-IBIG ID
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="
                            selectedApplicant?.spouse_name ||
                            selectedApplicant?.mother_name
                        "
                        class="grid grid-cols-1 md:grid-cols-2 gap-6"
                    >
                        <div v-if="selectedApplicant?.spouse_name">
                            <h3
                                class="text-xs font-black text-purple-600 uppercase tracking-widest mb-3"
                            >
                                Spouse Information
                            </h3>
                            <div
                                class="bg-slate-50 dark:bg-slate-900/40 p-6 rounded-2xl space-y-4"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Spouse Name
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            selectedApplicant.spouse_name ||
                                            "N/A"
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Occupation
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            selectedApplicant.spouse_occupation ||
                                            "N/A"
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Address
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            selectedApplicant.spouse_address ||
                                            "N/A"
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div v-if="selectedApplicant?.mother_name">
                            <h3
                                class="text-xs font-black text-purple-600 uppercase tracking-widest mb-3"
                            >
                                Parents
                            </h3>
                            <div
                                class="bg-slate-50 dark:bg-slate-900/40 p-6 rounded-2xl space-y-4"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Mother
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            selectedApplicant.mother_name ||
                                            "N/A"
                                        }}<br />{{
                                            selectedApplicant.mother_address
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Father
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            selectedApplicant.father_name ||
                                            "N/A"
                                        }}<br />{{
                                            selectedApplicant.father_address ||
                                            "N/A"
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedApplicant?.elementary_school">
                        <h3
                            class="text-xs font-black text-indigo-600 uppercase tracking-widest mb-3"
                        >
                            Educational Background
                        </h3>
                        <div
                            class="bg-slate-50 dark:bg-slate-900/40 p-6 rounded-2xl space-y-4"
                        >
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Elementary
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            selectedApplicant.elementary_school
                                        }}
                                        ({{ selectedApplicant.elementary_year }})
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        High School
                                    </p>
                                    <p class="text-sm">
                                        {{ selectedApplicant.high_school }} ({{ selectedApplicant.high_year }})
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        College
                                    </p>
                                    <p class="text-sm">
                                        {{ selectedApplicant.college }} ({{ selectedApplicant.college_year }})
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Vocational
                                    </p>
                                    <p class="text-sm">
                                        {{ selectedApplicant.vocational }} ({{ selectedApplicant.vocational_year }})
                                    </p>
                                </div>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400">
                                    Special Skills
                                </p>
                                <p class="text-sm">
                                    {{ selectedApplicant.special_skills || "N/A" }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="
                            selectedApplicant?.employment_records &&
                            selectedApplicant.employment_records.length
                        "
                    >
                        <h3
                            class="text-xs font-black text-amber-600 uppercase tracking-widest mb-3"
                        >
                            Employment Records
                        </h3>
                        <div
                            class="bg-slate-50 dark:bg-slate-900/40 p-6 rounded-2xl space-y-4"
                        >
                            <div
                                v-for="(
                                    rec, idx
                                ) in selectedApplicant.employment_records"
                                :key="idx"
                                class="border-b border-slate-200 dark:border-slate-700 pb-4 last:border-0"
                            >
                                <p class="text-sm font-bold">
                                    {{ rec.company }}
                                </p>
                                <p class="text-xs">
                                    Years: {{ rec.years }} | Salary:
                                    {{ rec.salary }} | Position:
                                    {{ rec.position }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    Reason: {{ rec.reason }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="px-8 py-6 border-t border-slate-100 dark:border-slate-700 flex justify-end"
                >
                    <button
                        @click="isViewModalOpen = false"
                        class="px-8 py-3 bg-slate-900 text-white rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-blue-600 transition-all"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Accept Modal -->
        <div
            v-if="isAcceptModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md"
            @click.self="isAcceptModalOpen = false"
        >
            <div
                class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-2xl max-w-md w-full overflow-hidden"
            >
                <div class="bg-emerald-600 p-8 text-white">
                    <h2 class="text-xl font-black uppercase">
                        Accept Application
                    </h2>
                    <p class="text-emerald-200 text-xs mt-1">
                        Assign applicant to a department for interview
                    </p>
                </div>
                <div class="p-8 space-y-6">
                    <div>
                        <label
                            class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block"
                            >Select Module</label
                        >
                        <select
                            v-model="selectedModule"
                            class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500"
                        >
                            <option value="" disabled>Choose department</option>
                            <option
                                v-for="mod in modules"
                                :key="mod.value"
                                :value="mod.value"
                            >
                                {{ mod.label }}
                            </option>
                        </select>
                    </div>
                    <div class="flex gap-3">
                        <button
                            @click="isAcceptModalOpen = false"
                            class="flex-1 py-4 text-slate-500 font-bold text-xs uppercase"
                        >
                            Cancel
                        </button>
                        <button
                            @click="acceptApplicant"
                            :disabled="!selectedModule"
                            class="flex-1 py-4 bg-emerald-600 text-white rounded-2xl text-xs font-black uppercase shadow-lg hover:bg-emerald-700 transition-all disabled:opacity-50"
                        >
                            Confirm
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div
            v-if="isRejectModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md"
            @click.self="isRejectModalOpen = false"
        >
            <div
                class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-2xl max-w-md w-full overflow-hidden"
            >
                <div class="bg-red-600 p-8 text-white">
                    <h2 class="text-xl font-black uppercase">
                        Reject Application
                    </h2>
                    <p class="text-red-200 text-xs mt-1">
                        Provide reason for rejection
                    </p>
                </div>
                <div class="p-8 space-y-6">
                    <div>
                        <label
                            class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block"
                            >Reason</label
                        >
                        <textarea
                            v-model="rejectionReason"
                            rows="3"
                            class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-red-500"
                            placeholder="e.g., Insufficient experience..."
                        ></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button
                            @click="isRejectModalOpen = false"
                            class="flex-1 py-4 text-slate-500 font-bold text-xs uppercase"
                        >
                            Cancel
                        </button>
                        <button
                            @click="rejectApplicant"
                            :disabled="!rejectionReason.trim()"
                            class="flex-1 py-4 bg-red-600 text-white rounded-2xl text-xs font-black uppercase shadow-lg hover:bg-red-700 transition-all disabled:opacity-50"
                        >
                            Confirm Rejection
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Applicant Modal -->
        <div
            v-if="isAddModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md"
            @click.self="isAddModalOpen = false"
        >
            <div
                class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col"
            >
                <div
                    class="px-8 py-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center sticky top-0 bg-white dark:bg-slate-800 z-10"
                >
                    <h2
                        class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tight"
                    >
                        Add New Applicant
                    </h2>
                    <button
                        @click="isAddModalOpen = false"
                        class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-full transition-colors"
                    >
                        <X class="h-5 w-5 text-slate-400" />
                    </button>
                </div>
                <div class="overflow-y-auto p-8 space-y-8">
                    <form @submit.prevent="addApplicant" class="space-y-10">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-3">
                                <h3
                                    class="text-sm font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 border-b border-slate-200 dark:border-slate-700 pb-2 mb-2"
                                >
                                    Personal Identity
                                </h3>
                            </div>

                            <div>
                                <InputLabel
                                    for="image"
                                    value="Profile Photo (Optional)"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <div
                                    class="relative h-32 rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 hover:border-blue-400 transition-all group overflow-hidden mt-1"
                                >
                                    <template v-if="!addForm.image">
                                        <Upload
                                            class="h-5 w-5 text-slate-400 group-hover:text-blue-500 transition-colors mb-2 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"
                                        />
                                        <input
                                            type="file"
                                            @change="handleImageUpload"
                                            class="absolute inset-0 opacity-0 cursor-pointer"
                                            accept="image/*"
                                        />
                                    </template>
                                    <template v-else>
                                        <div
                                            class="flex flex-col items-center justify-center h-full"
                                        >
                                            <div
                                                class="p-1.5 bg-emerald-500/20 rounded-full mb-1"
                                            >
                                                <FileCheck
                                                    class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                                                />
                                            </div>
                                            <p
                                                class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 truncate w-24"
                                            >
                                                {{ addForm.image.name }}
                                            </p>
                                            <button
                                                @click="addForm.image = null"
                                                type="button"
                                                class="mt-2 p-1.5 bg-red-500/20 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-500/40 transition-colors"
                                            >
                                                <Trash2 class="h-3 w-3" />
                                            </button>
                                        </div>
                                    </template>
                                </div>
                                <InputError
                                    class="mt-1 text-red-500"
                                    :message="addForm.errors.image"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    for="first_name"
                                    value="First Name"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="first_name"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.first_name"
                                    required
                                    placeholder="Juan"
                                    @keypress="
                                        blockNumbersAndSpecial(
                                            $event,
                                            'first_name',
                                        )
                                    "
                                />
                                <p
                                    v-if="inputWarnings.first_name"
                                    class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse"
                                >
                                    {{ inputWarnings.first_name }}
                                </p>
                                <InputError
                                    class="mt-1 text-red-500"
                                    :message="addForm.errors.first_name"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    for="middle_name"
                                    value="Middle Name (Optional)"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="middle_name"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.middle_name"
                                    placeholder="Santos"
                                    @keypress="
                                        blockNumbersAndSpecial(
                                            $event,
                                            'middle_name',
                                        )
                                    "
                                />
                                <p
                                    v-if="inputWarnings.middle_name"
                                    class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse"
                                >
                                    {{ inputWarnings.middle_name }}
                                </p>
                                <InputError
                                    class="mt-1 text-red-500"
                                    :message="addForm.errors.middle_name"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    for="last_name"
                                    value="Last Name"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="last_name"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.last_name"
                                    required
                                    placeholder="Dela Cruz"
                                    @keypress="
                                        blockNumbersAndSpecial(
                                            $event,
                                            'last_name',
                                        )
                                    "
                                />
                                <p
                                    v-if="inputWarnings.last_name"
                                    class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse"
                                >
                                    {{ inputWarnings.last_name }}
                                </p>
                                <InputError
                                    class="mt-1 text-red-500"
                                    :message="addForm.errors.last_name"
                                />
                            </div>

                            <div
                                class="md:col-span-3 mt-4 border-t border-slate-200 dark:border-slate-700 pt-4"
                            >
                                <h3
                                    class="text-sm font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 pb-2 mb-2"
                                >
                                    Professional & Contact Details
                                </h3>
                            </div>

                            <div>
                                <InputLabel
                                    for="email"
                                    value="Email Address"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.email"
                                    required
                                    placeholder="juan@example.com"
                                    @keypress="blockSpecialForEmail($event)"
                                />
                                <p
                                    v-if="inputWarnings.email"
                                    class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse"
                                >
                                    {{ inputWarnings.email }}
                                </p>
                            </div>

                            <div>
                                <InputLabel
                                    for="phone_raw"
                                    value="Phone Number (12 digits)"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <div class="flex gap-2 mt-1">
                                    <select
                                        v-model="addForm.phone_country"
                                        class="w-[35%] py-3 px-2 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    >
                                        <option value="+63">+63 (PH)</option>
                                        <option value="+1">+1 (US/CA)</option>
                                    </select>

                                    <TextInput
                                        id="phone_raw"
                                        type="tel"
                                        maxlength="12"
                                        class="w-[65%] py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.phone_raw"
                                        @input="enforceNumbersOnly"
                                        @keypress="blockNonNumbers"
                                        required
                                        placeholder="09123456789"
                                    />
                                </div>
                                <p
                                    v-if="inputWarnings.phone_raw"
                                    class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse"
                                >
                                    {{ inputWarnings.phone_raw }}
                                </p>
                            </div>

                            <div>
                                <InputLabel
                                    for="position_applied"
                                    value="Position Applied For"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <select
                                    id="position_applied"
                                    v-model="addForm.position_applied"
                                    required
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                >
                                    <option value="" disabled>Select Position</option>
                                     <!-- <option value="Staff">Staff</option>
                                      <option value="Manager">Manager</option> -->
                                    <option v-for="pos in activePositions" :key="pos.id" :value="pos.position">
                                        {{ pos.position }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <InputLabel
                                    for="notice_period"
                                    value="Notice Period"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <select
                                    id="notice_period"
                                    v-model="addForm.notice_period"
                                    required
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                >
                                    <option value="immediate">Immediate</option>
                                    <option value="15_Days">15 Days</option>
                                    <option value="30_Days">30 Days</option>
                                    <option value="60_Days">60 Days</option>
                                </select>
                            </div>

                            <div class="md:col-span-3">
                                <InputLabel
                                    for="street_address"
                                    value="Street Address"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="street_address"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.street_address"
                                    required
                                    placeholder="123 Main St, Brgy. San Jose"
                                    @keypress="blockSpecialForAddress($event)"
                                />
                                <p
                                    v-if="inputWarnings.street_address"
                                    class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse"
                                >
                                    {{ inputWarnings.street_address }}
                                </p>
                            </div>

                            <div>
                                <InputLabel
                                    for="city"
                                    value="City/Municipality"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="city"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.city"
                                    required
                                    placeholder="General Trias"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    for="state_province"
                                    value="State/Province"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="state_province"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.state_province"
                                    required
                                    placeholder="Cavite"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    for="postal_zip_code"
                                    value="Postal/Zip Code"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="postal_zip_code"
                                    type="text"
                                    maxlength="4"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.postal_zip_code"
                                    required
                                    placeholder="4107"
                                />
                                <p
                                    v-if="inputWarnings.postal_zip_code"
                                    class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse"
                                >
                                    {{ inputWarnings.postal_zip_code }}
                                </p>
                            </div>

                            <div
                                class="md:col-span-3 mt-4 border-t border-slate-200 dark:border-slate-700 pt-4"
                            >
                                <h3
                                    class="text-sm font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 pb-2 mb-2"
                                >
                                    Other Details
                                </h3>
                            </div>

                            <div>
                                <InputLabel
                                    for="date_of_birth"
                                    value="Date of Birth"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="date_of_birth"
                                    type="date"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.date_of_birth"
                                    :class="{'border-red-500': addForm.date_of_birth && !isOldEnough(addForm.date_of_birth)}"/>
                                     <p v-if="addForm.date_of_birth && !isOldEnough(addForm.date_of_birth)" class="text-[10px] text-red-500 font-bold mt-1 italic uppercase">
                                       APPLICANT MUST BE 18+ YEARS OLD
                                      </p>
                            </div>
                            <div>
                                <InputLabel
                                    for="place_of_birth"
                                    value="Place of Birth"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="place_of_birth"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.place_of_birth"
                                    placeholder="City, Province"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    for="citizenship"
                                    value="Citizenship"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="citizenship"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.citizenship"
                                    placeholder="Filipino"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    for="weight"
                                    value="Weight (kg)"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="weight"
                                    type="text"
                                    step="0.1"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.weight"
                                    placeholder="65.5"
                                    @keypress="(e) => {
                                            if (!/[0-9.]/.test(e.key)) e.preventDefault();
                                            if (e.key === '.' && addForm.weight.toString().includes('.')) e.preventDefault();
                                            }"
                                />
                            
                            </div>
                            <div>
                                <InputLabel
                                    for="height"
                                    value="Height (cm)"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="height"
                                    type="text"
                                    step="0.1"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.height"
                                    placeholder="170"
                                    @keypress="(e) => {
                                            if (!/[0-9.]/.test(e.key)) e.preventDefault();
                                            if (e.key === '.' && addForm.height.toString().includes('.')) e.preventDefault();
                                            }"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    for="civil_status"
                                    value="Civil Status"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <select
                                    id="civil_status"
                                    v-model="addForm.civil_status"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                >
                                    <option value="" disabled>Select</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Divorced">Divorced</option>
                                    <option value="Widowed">Widowed</option>
                                </select>
                            </div>

                            <div>
                                <InputLabel
                                    for="sex"
                                    value="Sex"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <select
                                    id="sex"
                                    v-model="addForm.sex"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                >
                                    <option value="" disabled>Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel
                                    for="religion"
                                    value="Religion"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="religion"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.religion"
                                    placeholder="Roman Catholic"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 mt-6">
                            <div>
                                <h3
                                    class="text-sm font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 border-b border-slate-200 dark:border-slate-700 pb-2 mb-2"
                                >
                                    Government IDs
                                </h3>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <InputLabel
                                        for="sss_number"
                                        value="SSS Number"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="sss_number"
                                        type="text"
                                        maxlength= 10
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.sss_number"
                                        placeholder="XX-XXXXXXX-X"
                                       @keypress="blockNonNumbers"
                                       @input="addForm.emergency_phone = $event.target.value.replace(/\D/g, '')"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="philhealth_number"
                                        value="PhilHealth Number"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="philhealth_number"
                                        type="text"
                                        maxlength=12
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.philhealth_number"
                                        placeholder="XX-XXXXXXXXX-X"
                                        @keypress="blockNonNumbers"
                                        @input="addForm.philhealth_number = $event.target.value.replace(/\D/g, '')"
                                        />
                                </div>
                                <div>
                                    <InputLabel
                                        for="pagibig_number"
                                        value="Pag-IBIG Number"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="pagibig_number"
                                        type="text"
                                        maxlength= 12
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.pagibig_number"
                                        placeholder="XXXX-XXXX-XXXX"
                                        @keypress="blockNonNumbers"
                                            @input="addForm.pagibig_numbers = $event.target.value.replace(/\D/g, '')"
                                        />
                                                                        </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div
                                    v-for="type in [
                                        'sss',
                                        'philhealth',
                                        'pagibig',
                                    ]"
                                    :key="type"
                                    class="space-y-2"
                                >
                                    <p
                                        class="text-[10px] font-black text-slate-500 uppercase ml-1"
                                    >
                                        {{ type.toUpperCase() }} ID Image
                                    </p>
                                    <div
                                        :class="
                                            addForm[type + '_file']
                                                ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/10'
                                                : 'border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900'
                                        "
                                        class="relative h-24 rounded-2xl border-2 border-dashed flex flex-col items-center justify-center p-2 group transition-all"
                                    >
                                        <template
                                            v-if="!addForm[type + '_file']"
                                        >
                                            <Upload
                                                class="h-4 w-4 text-slate-400 group-hover:text-blue-500"
                                            />
                                            <input
                                                type="file"
                                                @change="
                                                    (e) =>
                                                        handleFileUpload(
                                                            e,
                                                            type + '_file',
                                                        )
                                                "
                                                class="absolute inset-0 opacity-0 cursor-pointer"
                                                accept=".jpg,.jpeg,.png,.pdf"
                                            />
                                        </template>
                                        <template v-else>
                                            <FileCheck
                                                class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                                            />
                                            <p
                                                class="text-[8px] font-bold text-emerald-600 dark:text-emerald-400 truncate w-20 text-center mt-1"
                                            >
                                                {{
                                                    addForm[type + "_file"].name
                                                }}
                                            </p>
                                            <button
                                                @click="
                                                    removeFile(type + '_file')
                                                "
                                                type="button"
                                                class="mt-1 text-rose-500"
                                            >
                                                <Trash2 class="h-3 w-3" />
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 mt-6">
                            <div>
                                <h3
                                    class="text-sm font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 border-b border-slate-200 dark:border-slate-700 pb-2 mb-2"
                                >
                                    Spouse Information (if married)
                                </h3>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel
                                        for="spouse_name"
                                        value="Spouse's Full Name"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="spouse_name"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.spouse_name"
                                        placeholder="Juan Dela Cruz"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="spouse_occupation"
                                        value="Occupation"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="spouse_occupation"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.spouse_occupation"
                                        placeholder="Engineer"
                                    />
                                </div>
                                <div class="md:col-span-2">
                                    <InputLabel
                                        for="spouse_address"
                                        value="Address"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="spouse_address"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.spouse_address"
                                        placeholder="Complete address"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 mt-6">
                            <div>
                                <h3
                                    class="text-sm font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 border-b border-slate-200 dark:border-slate-700 pb-2 mb-2"
                                >
                                    Children
                                </h3>
                            </div>
                            <div>
                                <button
                                    type="button"
                                    @click="addChild"
                                    class="inline-flex items-center gap-1 px-4 py-2 bg-blue-600/10 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-600 hover:text-white transition-all"
                                >
                                    + Add Child
                                </button>
                            </div>
                            <div
                                v-for="(child, idx) in childrenList"
                                :key="idx"
                                class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-slate-50 dark:bg-slate-900/40 rounded-xl relative"
                            >
                                <button
                                    type="button"
                                    @click="removeChild(idx)"
                                    class="absolute top-2 right-2 p-1 text-red-400 hover:text-red-600"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                                <div>
                                    <InputLabel
                                        :for="`child_name_${idx}`"
                                        value="Name"
                                        class="text-slate-600 dark:text-slate-400 text-xs"
                                    />
                                    <TextInput
                                        :id="`child_name_${idx}`"
                                        type="text"
                                        class="mt-1 w-full py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="child.name"
                                        placeholder="Full name"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        :for="`child_dob_${idx}`"
                                        value="Date of Birth"
                                        class="text-slate-600 dark:text-slate-400 text-xs"
                                    />
                                    <TextInput
                                        :id="`child_dob_${idx}`"
                                        type="date"
                                        class="mt-1 w-full py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="child.dob"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 mt-6">
                            <div>
                                <h3
                                    class="text-sm font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 border-b border-slate-200 dark:border-slate-700 pb-2 mb-2"
                                >
                                    Parents Information
                                </h3>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel
                                        for="mother_name"
                                        value="Mother's Name"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="mother_name"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.mother_name"
                                        placeholder="Full name"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="mother_address"
                                        value="Mother's Address"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="mother_address"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.mother_address"
                                        placeholder="Address"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="father_name"
                                        value="Father's Name"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="father_name"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.father_name"
                                        placeholder="Full name"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="father_address"
                                        value="Father's Address"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="father_address"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.father_address"
                                        placeholder="Address"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <InputLabel
                                for="languages"
                                value="Language(s) You Can Speak or Write"
                                class="text-slate-700 dark:text-slate-300 font-semibold"
                            />
                            <TextInput
                                id="languages"
                                type="text"
                                class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                v-model="addForm.languages"
                                placeholder="Tagalog, English, etc."
                            />
                        </div>

                        <div class="grid grid-cols-1 gap-6 mt-6">
                            <div>
                                <h3
                                    class="text-sm font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 border-b border-slate-200 dark:border-slate-700 pb-2 mb-2"
                                >
                                    Emergency Contact
                                </h3>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel
                                        for="emergency_name"
                                        value="Name"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="emergency_name"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.emergency_name"
                                        placeholder="Full name"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="emergency_relationship"
                                        value="Relationship"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="emergency_relationship"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.emergency_relationship"
                                        placeholder="Spouse, Parent, etc."
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="emergency_phone"
                                        value="Telephone Number"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="emergency_phone"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.emergency_phone"
                                        placeholder="09123456789"
                                         @keypress="blockNonNumbers"
                                         @input="addForm.emergency_phone = $event.target.value.replace(/\D/g, '')"

                                    />
                                </div>
                                <div class="md:col-span-2">
                                    <InputLabel
                                        for="emergency_address"
                                        value="Address"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="emergency_address"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.emergency_address"
                                        placeholder="Complete address"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 mt-6">
                            <div>
                                <h3
                                    class="text-sm font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 border-b border-slate-200 dark:border-slate-700 pb-2 mb-2"
                                >
                                    Educational Background
                                </h3>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel
                                        for="elementary_school"
                                        value="Elementary School"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="elementary_school"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.elementary_school"
                                        placeholder="School name"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="elementary_year"
                                        value="Year Graduated"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="elementary_year"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.elementary_year"
                                        placeholder="YYYY"
                                        @keypress="blockNonNumbers"
                                        @input="elementary_year = $event.target.value.replace(/\D/g, '')"

                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="high_school"
                                        value="High School"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="high_school"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.high_school"
                                        placeholder="School name"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="high_year"
                                        value="Year Graduated"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="high_year"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.high_year"
                                        placeholder="YYYY"
                                        @keypress="blockNonNumbers"
                                       @input="high_year = $event.target.value.replace(/\D/g, '')"

                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="college"
                                        value="College"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="college"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.college"
                                        placeholder="Course & School"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="college_year"
                                        value="Year Graduated"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="college_year"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.college_year"
                                        placeholder="YYYY"
                                        @keypress="blockNonNumbers"
                                        @input="college_year = $event.target.value.replace(/\D/g, '')"

                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="vocational"
                                        value="Vocational"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="vocational"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.vocational"
                                        placeholder="Course & School"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="vocational_year"
                                        value="Year Graduated"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="vocational_year"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="addForm.vocational_year"
                                        placeholder="YYYY"
                                    />
                                </div>
                            </div>
                            <div>
                                <InputLabel
                                    for="special_skills"
                                    value="Special Skills"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="special_skills"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.special_skills"
                                    placeholder="e.g., Microsoft Office, Sewing"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 mt-6">
                            <div class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="has_employment_record"
                                    v-model="showEmploymentSection"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                                <label
                                    for="has_employment_record"
                                    class="text-sm font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    I have previous employment record(s)
                                </label>
                            </div>
                            <div v-if="showEmploymentSection">
                                <div
                                    class="flex justify-between items-center mb-2"
                                >
                                    <h4
                                        class="text-xs font-black text-blue-600 uppercase"
                                    >
                                        Employment Records (from present to
                                        previous)
                                    </h4>
                                    <button
                                        type="button"
                                        @click="addEmployment"
                                        class="inline-flex items-center gap-1 px-3 py-1 bg-blue-600/10 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 rounded-lg text-xs font-bold uppercase hover:bg-blue-600 hover:text-white transition-all"
                                    >
                                        + Add Record
                                    </button>
                                </div>
                                <div
                                    v-for="(rec, idx) in employmentList"
                                    :key="idx"
                                    class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-slate-50 dark:bg-slate-900/40 rounded-xl relative mb-4 border border-slate-100 dark:border-slate-700"
                                >
                                    <button
                                        type="button"
                                        @click="removeEmployment(idx)"
                                        class="absolute top-2 right-2 p-1 text-red-400 hover:text-red-600"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                    <div>
                                        <InputLabel
                                            :for="`emp_company_${idx}`"
                                            value="Company"
                                            class="text-xs text-slate-600 dark:text-slate-400"
                                        />
                                        <TextInput
                                            :id="`emp_company_${idx}`"
                                            type="text"
                                            class="mt-1 w-full py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                            v-model="rec.company"
                                            placeholder="Company name"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel
                                            :for="`emp_years_${idx}`"
                                            value="Years of Service"
                                            class="text-xs text-slate-600 dark:text-slate-400"
                                        />
                                        <TextInput
                                            :id="`emp_years_${idx}`"
                                            type="text"
                                            class="mt-1 w-full py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                            v-model="rec.years"
                                            placeholder="e.g., 2 years"
                                             @keypress="blockNonNumbers"
                                             @input="emp_years = $event.target.value.replace(/\D/g, '')"

                                        />
                                    </div>
                                    <div>
                                        <InputLabel
                                            :for="`emp_salary_${idx}`"
                                            value="Salary"
                                            class="text-xs text-slate-600 dark:text-slate-400"
                                        />
                                        <TextInput
                                            :id="`emp_salary_${idx}`"
                                            type="text"
                                            class="mt-1 w-full py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                            v-model="rec.salary"
                                            placeholder="Monthly"
                                              @keypress="blockNonNumbers"
                                             @input="emp_salary = $event.target.value.replace(/\D/g, '')"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel
                                            :for="`emp_position_${idx}`"
                                            value="Position"
                                            class="text-xs text-slate-600 dark:text-slate-400"
                                        />
                                        <TextInput
                                            :id="`emp_position_${idx}`"
                                            type="text"
                                            class="mt-1 w-full py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                            v-model="rec.position"
                                            placeholder="Job title"
                                        />
                                    </div>
                                    <div class="md:col-span-2">
                                        <InputLabel
                                            :for="`emp_reason_${idx}`"
                                            value="Reason for Leaving"
                                            class="text-xs text-slate-600 dark:text-slate-400"
                                        />
                                        <TextInput
                                            :id="`emp_reason_${idx}`"
                                            type="text"
                                            class="mt-1 w-full py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                            v-model="rec.reason"
                                            placeholder="Reason"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 mt-6">
                            <div>
                                <InputLabel
                                    for="machine_operation"
                                    value="Any machine you can operate?"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="machine_operation"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.machine_operation"
                                    placeholder="e.g., Sewing machine, Forklift"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    for="referred_by"
                                    value="Who referred you to this company?"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="referred_by"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.referred_by"
                                    placeholder="Name"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    for="referred_by_address"
                                    value="His/Her address"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="referred_by_address"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.referred_by_address"
                                    placeholder="Address"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 mt-6">
                            <div>
                                <h3
                                    class="text-sm font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 border-b border-slate-200 dark:border-slate-700 pb-2 mb-2"
                                >
                                    Previous Employment with Monti Textile?
                                </h3>
                            </div>
                            <div>
                                <InputLabel
                                    for="previous_employment_company"
                                    value="Have you ever been employed in this company?"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <select
                                    id="previous_employment_company"
                                    v-model="
                                        addForm.previous_employment_company
                                    "
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                >
                                    <option value="" disabled>Select</option>
                                    <option value="yes">Yes</option>
                                    <option value="no">No</option>
                                </select>
                            </div>
                            <div
                                v-if="
                                    addForm.previous_employment_company ===
                                    'yes'
                                "
                                class="grid grid-cols-1 md:grid-cols-3 gap-6"
                            >
                                <div>
                                    <InputLabel
                                        for="previous_employment_when"
                                        value="When?"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="previous_employment_when"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="
                                            addForm.previous_employment_when
                                        "
                                        placeholder="e.g., 2020-2021"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="previous_employment_position"
                                        value="Position"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="previous_employment_position"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="
                                            addForm.previous_employment_position
                                        "
                                        placeholder="Position held"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="previous_employment_department"
                                        value="Department"
                                        class="text-slate-700 dark:text-slate-300 font-semibold"
                                    />
                                    <TextInput
                                        id="previous_employment_department"
                                        type="text"
                                        class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                        v-model="
                                            addForm.previous_employment_department
                                        "
                                        placeholder="Department"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 mt-6">
                            <div>
                                <h3
                                    class="text-sm font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 border-b border-slate-200 dark:border-slate-700 pb-2 mb-2"
                                >
                                    Related Employees
                                </h3>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 mb-2">
                                    Are you at present or in the past related
                                    (by consanguinity or affinity) with any
                                    employee/worker of Monti Textile? If yes,
                                    please list:
                                </p>
                            </div>
                            <div>
                                <InputLabel
                                    for="related_employees"
                                    value="Name — Relationship"
                                    class="text-slate-700 dark:text-slate-300 font-semibold"
                                />
                                <TextInput
                                    id="related_employees"
                                    type="text"
                                    class="mt-1 w-full py-3 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700"
                                    v-model="addForm.related_employees"
                                    placeholder="e.g., Juan Dela Cruz - Cousin"
                                />
                            </div>
                        </div>

                        <div
                            class="flex justify-end gap-3 pt-8 border-t border-slate-200 dark:border-slate-700 mt-8"
                        >
                            <button
                                type="button"
                                @click="isAddModalOpen = false"
                                class="px-6 py-3 text-slate-500 font-bold text-xs uppercase hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-all"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="addForm.processing"
                                class="px-8 py-3 bg-blue-600 text-white rounded-2xl text-xs font-black uppercase shadow-lg hover:bg-blue-700 transition-all disabled:opacity-50 flex items-center gap-2"
                            >
                                <span v-if="addForm.processing"
                                    >Processing...</span
                                >
                                <span v-else class="flex items-center gap-2">
                                    <Save class="w-4 h-4" /> Save Applicant
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Position Management Modal -->
        <div
            v-if="isPositionModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md"
            @click.self="closePositionModal"
        >
            <div
                class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]"
            >
                <div
                    class="px-8 py-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center sticky top-0 bg-white dark:bg-slate-800 z-10"
                >
                    <h2
                        class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tight flex items-center gap-2"
                    >
                        <BookPlus class="h-6 w-6 text-blue-600" />
                        Manage Positions
                    </h2>
                    <button
                        @click="closePositionModal"
                        class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-full transition-colors"
                    >
                        <X class="h-5 w-5 text-slate-400" />
                    </button>
                </div>
                <div class="overflow-y-auto p-8 flex-1">
                    <form
                        @submit.prevent="submitPosition"
                        class="flex flex-col sm:flex-row gap-3 mb-8"
                    >
                        <input
                            v-model="newPositionTitle"
                            type="text"
                            placeholder="Enter new position title..."
                            class="flex-1 rounded-xl border border-slate-300 dark:border-slate-600 bg-transparent px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:text-white outline-none transition-all"
                            required
                            :disabled="isSubmittingPosition"
                        />
                        <button
                            type="submit"
                            :disabled="
                                isSubmittingPosition || !newPositionTitle
                            "
                            class="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 shadow-lg"
                        >
                            <span v-if="isSubmittingPosition">Saving...</span>
                            <span v-else>Save Position</span>
                        </button>
                    </form>

                    <div class="space-y-4">
                        <h3
                            class="text-sm font-black uppercase tracking-widest text-slate-900 dark:text-white"
                        >
                            Existing Positions
                        </h3>
                        <div
                            class="border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden"
                        >
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400"
                                >
                                    <tr>
                                        <th class="px-6 py-4 font-bold uppercase tracking-wider text-[10px]">
                                            Position Name
                                        </th>
                                        <th class="px-6 py-4 font-bold uppercase tracking-wider text-[10px] w-24">
                                            Status
                                        </th>
                                        <th class="px-6 py-4 font-bold uppercase tracking-wider text-[10px] text-right w-48">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-slate-800">
                                    <tr v-if="isLoadingPositions">
                                        <td colspan="3" class="px-6 py-8 text-center text-slate-500 font-medium">
                                            Loading positions...
                                        </td>
                                    </tr>
                                    <tr v-else-if="positions.length === 0">
                                        <td colspan="3" class="px-6 py-8 text-center text-slate-500 font-medium">
                                            No positions found. Create one above.
                                        </td>
                                    </tr>
                                    <tr
                                        v-else
                                        v-for="pos in positions"
                                        :key="pos.id"
                                        class="hover:bg-slate-50 dark:hover:bg-slate-900/20 transition-colors"
                                    >
                                        <td class="px-6 py-4 font-semibold text-slate-700 dark:text-slate-200">
                                            {{ pos.position }}
                                        </td>

                                        <td class="px-6 py-4">
                                            <span
                                                :class="[
                                                    pos.status === 'active' 
                                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' 
                                                        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                                    'px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider'
                                                ]"
                                            >
                                                {{ pos.status }}
                                            </span>
                                        </td>

                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    @click="openEditPositionModal(pos)"
                                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-amber-600 bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/20 dark:hover:bg-amber-900/40"
                                                    title="Edit Position"
                                                >
                                                    <Edit class="h-4 w-4" /> Edit
                                                </button>
                                                <button
                                                    @click="toggleStatus(pos.id)"
                                                    :class="[
                                                        'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all',
                                                        pos.status === 'active' 
                                                            ? 'text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40' 
                                                            : 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:hover:bg-emerald-900/40'
                                                    ]"
                                                    :title="pos.status === 'active' ? 'Deactivate Position' : 'Activate Position'"
                                                >
                                                    <XCircle v-if="pos.status === 'active'" class="h-4 w-4" />
                                                    <CheckCircle v-else class="h-4 w-4" />
                                                    {{ pos.status === 'active' ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div
                    class="px-8 py-6 border-t border-slate-100 dark:border-slate-700 flex justify-end bg-white dark:bg-slate-800"
                >
                    <button
                        @click="closePositionModal"
                        class="px-8 py-3 bg-slate-900 text-white rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-blue-600 transition-all"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Position Modal -->
        <div
            v-if="isEditPositionModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md"
            @click.self="isEditPositionModalOpen = false"
        >
            <div
                class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-2xl max-w-md w-full overflow-hidden"
            >
                <div class="bg-amber-600 p-8 text-white">
                    <h2 class="text-xl font-black uppercase">Edit Position</h2>
                    <p class="text-amber-200 text-xs mt-1">Update the position title</p>
                </div>
                <div class="p-8 space-y-6">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Position Name</label>
                        <input
                            v-model="editPositionTitle"
                            type="text"
                            class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-900 border-none ring-1 ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-amber-500"
                            placeholder="e.g., Senior Developer"
                        />
                    </div>
                    <div class="flex gap-3">
                        <button
                            @click="isEditPositionModalOpen = false"
                            class="flex-1 py-4 text-slate-500 font-bold text-xs uppercase"
                        >
                            Cancel
                        </button>
                        <button
                            @click="updatePosition"
                            :disabled="!editPositionTitle.trim()"
                            class="flex-1 py-4 bg-amber-600 text-white rounded-2xl text-xs font-black uppercase shadow-lg hover:bg-amber-700 transition-all disabled:opacity-50"
                        >
                            Update
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Image Viewer Modal -->
        <div
            v-if="isImageViewerOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-md"
            @click.self="isImageViewerOpen = false"
        >
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-3xl max-h-[90vh] overflow-hidden flex flex-col"
            >
                <div
                    class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center"
                >
                    <h3 class="text-lg font-bold">Document Preview</h3>
                    <button
                        @click="isImageViewerOpen = false"
                        class="p-1 hover:bg-slate-100 rounded-full transition-colors"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="p-4 flex items-center justify-center">
                    <img :src="currentImageUrl" alt="Document" class="max-w-full max-h-[70vh] object-contain" />
                </div>
                <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end">
                    <button
                        @click="isImageViewerOpen = false"
                        class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold uppercase"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}
</style>