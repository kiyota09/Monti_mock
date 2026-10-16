<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { initTheme } from '@/composables/useTheme';

const props = defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
    careerJobs?: Array<{
        id: number;
        posting_id: string;
        
        // Job Posting Fields
        title: string;
        work_location: string;
        work_mode: string;
        work_schedule: string;
        salary_visibility: string;
        salary_min: number | null;
        salary_max: number | null;
        salary_range: string;
        closing_date: string | null;
        closing_date_raw: string | null;
        published_date: string | null;
        published_date_raw: string | null;
        posted: string;
        posted_timestamp: number | null;
        created_at: string | null;
        
        // Position Fields
        position_name: string;
        position_code: string;
        department: string;
        job_description: string;
        responsibilities: string | null;
        qualifications: string | null;
        required_skills: string | null;
        employment_type: string;
        reports_to: string | null;
        management_level: string | null;
        
        // Vacancy Info
        vacancies: number;
        filled_vacancies: number;
        available_vacancies: number;
        
        // Status
        status: string;
        hiring_priority: string;
        is_urgent: boolean;
    }>;
}>();

const hasRoute = (routeName: string): boolean => {
    try {
        return typeof route === 'function' && route().has(routeName);
    } catch (e) {
        return false;
    }
};

const getSafeRoute = (routeName: string, fallbackPath: string): string => {
    return hasRoute(routeName) ? route(routeName) : fallbackPath;
};

const activePage = ref('home');
const searchQuery = ref('');
const filterType = ref('');
const selectedJob = ref<any>(null);
const showJobDetail = ref(false);

// Store jobs with real-time time updates
const jobs = ref(props.careerJobs || []);
const currentTime = ref(new Date());

// Update time every minute
let timeInterval: number | null = null;

onMounted(() => {
    // Stored user theme wins; otherwise fall back to the OS/browser theme.
    initTheme();
    // Initial load
    if (props.careerJobs) {
        jobs.value = props.careerJobs;
    }
    
    // Start timer to update relative times
    timeInterval = window.setInterval(() => {
        currentTime.value = new Date();
    }, 60000); // Update every minute
});

onUnmounted(() => {
    if (timeInterval) {
        clearInterval(timeInterval);
    }
});

// Watch for prop changes
watch(() => props.careerJobs, (newVal) => {
    if (newVal) {
        jobs.value = newVal;
    }
}, { deep: true });

// Get unique employment types for filter
const employmentTypes = computed(() => {
    const types = new Set<string>();
    jobs.value.forEach(job => {
        if (job.employment_type) {
            types.add(job.employment_type);
        }
    });
    return Array.from(types);
});

// Get relative time function with proper calculation
const getRelativeTime = (dateString: string | null): string => {
    if (!dateString) return 'Recently';
    
    try {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        
        // If date is in the future or invalid
        if (diffMs < 0) return 'Just now';
        
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        const diffMonths = Math.floor(diffDays / 30);
        const diffYears = Math.floor(diffDays / 365);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return diffMins + 'm ago';
        if (diffHours < 24) return diffHours + 'h ago';
        if (diffDays < 7) return diffDays + 'd ago';
        if (diffDays < 30) return Math.floor(diffDays / 7) + 'w ago';
        if (diffMonths < 12) return diffMonths + 'mo ago';
        return diffYears + 'y ago';
    } catch (e) {
        return 'Recently';
    }
};

// Get the posted date string for display
const getPostedDisplay = (job: any): string => {
    // Use published_date_raw if available (has time)
    if (job.published_date_raw) {
        return getRelativeTime(job.published_date_raw);
    }
    // Fallback to created_at
    if (job.created_at) {
        return getRelativeTime(job.created_at);
    }
    // Fallback to the posted string from server
    return job.posted || 'Recently';
};

const filteredJobs = computed(() => {
    let result = jobs.value;
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(job =>
            job.title.toLowerCase().includes(query) ||
            job.department.toLowerCase().includes(query) ||
            job.position_name.toLowerCase().includes(query) ||
            (job.required_skills && job.required_skills.toLowerCase().includes(query)) ||
            (job.job_description && job.job_description.toLowerCase().includes(query)) ||
            (job.employment_type && job.employment_type.toLowerCase().includes(query))
        );
    }
    if (filterType.value) {
        result = result.filter(job => job.employment_type === filterType.value);
    }
    return result;
});

// FIXED: Apply function - redirect to apply page with position
const applyForJob = (job: any) => {
    // Redirect to apply page with position as parameter
    const positionName = job.position_name || job.title;
    router.visit('/apply?position=' + encodeURIComponent(positionName));
};

const viewJobDetail = (job: any) => {
    selectedJob.value = job;
    showJobDetail.value = true;
    document.body.style.overflow = 'hidden';
};

const closeJobDetail = () => {
    showJobDetail.value = false;
    selectedJob.value = null;
    document.body.style.overflow = '';
};

const switchPage = (page: string) => {
    activePage.value = page;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (page === 'careers') {
        showJobDetail.value = false;
        selectedJob.value = null;
    }
};

const formatSalary = (min: number | null, max: number | null, visibility: string): string => {
    if (visibility === 'Hide') return 'Confidential';
    if (visibility === 'Show on Application') return 'Disclosed upon application';
    if (min && max) return `₱${Number(min).toLocaleString()} - ₱${Number(max).toLocaleString()}`;
    if (min) return `From ₱${Number(min).toLocaleString()}`;
    if (max) return `Up to ₱${Number(max).toLocaleString()}`;
    return 'Negotiable';
};

const getEmploymentTypeBadge = (type: string): string => {
    const map: Record<string, string> = {
        'Full-time': 'bg-emerald-500/20 text-emerald-400 border-emerald-400/30',
        'Part-time': 'bg-blue-500/20 text-blue-400 border-blue-400/30',
        'Contract': 'bg-amber-500/20 text-amber-400 border-amber-400/30',
        'Temporary': 'bg-purple-500/20 text-purple-400 border-purple-400/30',
        'Internship': 'bg-rose-500/20 text-rose-400 border-rose-400/30',
        'Freelance': 'bg-indigo-500/20 text-indigo-400 border-indigo-400/30',
    };
    return map[type] || 'bg-slate-500/20 text-slate-400 border-slate-400/30';
};

const getEmploymentTypeIcon = (type: string): string => {
    const map: Record<string, string> = {
        'Full-time': '💼',
        'Part-time': '⏰',
        'Contract': '📄',
        'Temporary': '📅',
        'Internship': '🎓',
        'Freelance': '💻',
    };
    return map[type] || '📋';
};

const getWorkModeIcon = (mode: string): string => {
    const map: Record<string, string> = {
        'On-site': '🏢',
        'Remote': '🏠',
        'Hybrid': '🔄',
    };
    return map[mode] || '📍';
};

const services = [
    {
        icon: '💻',
        title: 'Software Development',
        description: 'Custom enterprise software solutions tailored to your business needs.',
        features: ['ERP Systems', 'Manufacturing Software', 'Custom Applications']
    },
    {
        icon: '🌐',
        title: 'Web Development',
        description: 'Modern, responsive web applications with cutting-edge technology stacks.',
        features: ['React/Vue.js', 'Laravel/PHP', 'Responsive Design']
    },
    {
        icon: '📱',
        title: 'Mobile Apps',
        description: 'Native and cross-platform mobile applications for iOS and Android.',
        features: ['React Native', 'Flutter', 'iOS/Android']
    },
    {
        icon: '☁️',
        title: 'Cloud Solutions',
        description: 'Scalable cloud infrastructure and migration services.',
        features: ['AWS/Azure/GCP', 'Cloud Migration', 'DevOps']
    },
    {
        icon: '📊',
        title: 'IT Consulting',
        description: 'Strategic technology consulting to drive digital transformation.',
        features: ['Strategy', 'Digital Transformation', 'IT Advisory']
    }
];

const leadership = [
    {
        name: 'Maria Santos',
        role: 'CEO & Founder',
        bio: '20+ years in textile manufacturing and technology.',
        avatar: 'MS'
    },
    {
        name: 'John Reyes',
        role: 'CTO',
        bio: 'Expert in enterprise software architecture.',
        avatar: 'JR'
    },
    {
        name: 'Anna Tan',
        role: 'Head of Operations',
        bio: 'Manufacturing excellence and supply chain expert.',
        avatar: 'AT'
    }
];

const coreValues = [
    { title: 'Innovation', icon: '💡', description: 'Pushing boundaries in textile manufacturing technology.' },
    { title: 'Quality', icon: '⭐', description: 'Uncompromising commitment to excellence.' },
    { title: 'Integrity', icon: '🤝', description: 'Honest and transparent partnerships.' },
    { title: 'Sustainability', icon: '🌱', description: 'Eco-friendly manufacturing practices.' }
];
</script>

<template>
    <Head title="Monti ERP | Industrial Textile Management" />

    <div class="welcome-scope relative min-h-screen w-full flex flex-col font-sans overflow-y-auto"
        style="background-image: url('/images/landingTheme.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed; background-color: #2a2a3e;">

        <div class="absolute inset-0 bg-black/30 backdrop-blur-[1px]"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-black/10 via-transparent to-black/10 pointer-events-none"></div>

        <!-- HEADER -->
        <header class="w-full border-b border-white/10 shrink-0 sticky top-0 bg-black/20 backdrop-blur-md z-20">
            <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-4 py-3">
                <div class="flex items-center gap-3 group cursor-pointer" @click="switchPage('home')">
                    <div class="p-1.5 bg-white/10 backdrop-blur-md rounded-lg border border-white/20 shadow-lg group-hover:scale-105 transition-transform duration-300">
                        <img src="/images/applogo.png" alt="Logo" class="h-7 w-7 sm:h-8 sm:w-8 object-contain" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-base sm:text-lg font-black tracking-tight leading-none text-white uppercase drop-shadow-md">
                            MONTI<span class="text-blue-400">TEXTILE</span>
                        </span>
                        <span class="text-[7px] sm:text-[8px] font-bold uppercase tracking-[0.2em] text-slate-300 mt-0.5">
                            Manufacturing ERP
                        </span>
                    </div>
                </div>

                <nav class="flex flex-wrap items-center gap-1.5 sm:gap-3">
                    <button @click="switchPage('home')" class="text-[9px] sm:text-xs font-semibold px-2 py-1 rounded-lg transition-all" :class="activePage === 'home' ? 'text-white bg-white/10' : 'text-slate-300 hover:text-white hover:bg-white/5'">
                        Home
                    </button>
                    <button @click="switchPage('about')" class="text-[9px] sm:text-xs font-semibold px-2 py-1 rounded-lg transition-all" :class="activePage === 'about' ? 'text-white bg-white/10' : 'text-slate-300 hover:text-white hover:bg-white/5'">
                        About
                    </button>
                    <button @click="switchPage('services')" class="text-[9px] sm:text-xs font-semibold px-2 py-1 rounded-lg transition-all" :class="activePage === 'services' ? 'text-white bg-white/10' : 'text-slate-300 hover:text-white hover:bg-white/5'">
                        Services
                    </button>
                    <button @click="switchPage('careers')" class="text-[9px] sm:text-xs font-semibold px-2 py-1 rounded-lg transition-all" :class="activePage === 'careers' ? 'text-white bg-white/10' : 'text-slate-300 hover:text-white hover:bg-white/5'">
                        Careers
                    </button>
                    <button @click="switchPage('contact')" class="text-[9px] sm:text-xs font-semibold px-2 py-1 rounded-lg transition-all" :class="activePage === 'contact' ? 'text-white bg-white/10' : 'text-slate-300 hover:text-white hover:bg-white/5'">
                        Contact
                    </button>

                    <span class="w-px h-4 bg-white/20 hidden sm:block"></span>

                    <!-- Login Button -->
                    <Link :href="getSafeRoute('client.login', '/login')"
                        class="text-[9px] sm:text-xs font-bold bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded-lg transition-all shadow-lg shadow-blue-600/30 active:scale-95">
                        Login
                    </Link>

                    <!-- Applicant Login Button -->
                    <Link :href="getSafeRoute('applicant.login', '/applicant/login')"
                        class="text-[9px] sm:text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-lg transition-all shadow-lg shadow-emerald-600/30 active:scale-95">
                        Applicant Login
                    </Link>

                    <!-- Agency Login Button -->
                    <Link :href="getSafeRoute('agency.login', '/agency/login')"
                        class="text-[9px] sm:text-xs font-bold bg-amber-600 hover:bg-amber-500 text-white px-3 py-1.5 rounded-lg transition-all shadow-lg shadow-amber-600/30 active:scale-95">
                        Agency Login
                    </Link>

                    <!-- Be our Partner (Register) Button -->
                    <Link v-if="canRegister" :href="getSafeRoute('client.register', '/register')"
                        class="text-[9px] sm:text-xs font-bold bg-white/15 backdrop-blur-md border border-white/30 text-white px-3 py-1.5 rounded-lg hover:bg-white/25 transition-all shadow-lg active:scale-95">
                        Be our Partner
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <div class="relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col min-h-screen">

            <!-- HOME PAGE -->
            <div v-if="activePage === 'home'" class="flex-1 flex flex-col py-4">
                <section class="text-center max-w-3xl mx-auto mb-8 shrink-0">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/15 backdrop-blur-md border border-blue-400/20 text-blue-300 text-[8px] sm:text-[9px] font-bold uppercase tracking-widest mb-4 shadow-lg">
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-blue-400"></span>
                        </span>
                        Enterprise Resource Planning
                    </div>
                    <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-black tracking-tight text-white leading-[1.1] mb-3 drop-shadow-2xl">
                        Let's <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-400">Weave</span><br />
                        The Future.
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300 max-w-2xl mx-auto leading-relaxed drop-shadow-md">
                        Precision management for Monti Textile's manufacturing lifecycle. From raw weaving to global
                        distribution, keep every thread accounted for.
                    </p>
                    <div class="flex flex-wrap justify-center gap-3 mt-5">
                        <button @click="switchPage('about')" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold transition-all shadow-lg shadow-blue-600/30 active:scale-95">
                            Learn More
                        </button>
                        <button @click="switchPage('contact')" class="px-5 py-2 bg-white/10 backdrop-blur-md border border-white/20 text-white rounded-lg text-xs font-bold hover:bg-white/20 transition-all">
                            Contact Us
                        </button>
                    </div>
                </section>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 max-w-3xl mx-auto w-full mb-8 shrink-0">
                    <div class="text-center border border-white/15 rounded-xl p-3.5 bg-white/5 backdrop-blur-sm hover:bg-white/10 transition-all duration-300 hover:border-blue-400/30">
                        <div class="text-xl font-bold text-white">10K+</div>
                        <div class="text-[8px] text-slate-400 uppercase tracking-wider mt-0.5">Products</div>
                    </div>
                    <div class="text-center border border-white/15 rounded-xl p-3.5 bg-white/5 backdrop-blur-sm hover:bg-white/10 transition-all duration-300 hover:border-blue-400/30">
                        <div class="text-xl font-bold text-white">500+</div>
                        <div class="text-[8px] text-slate-400 uppercase tracking-wider mt-0.5">Partners</div>
                    </div>
                    <div class="text-center border border-white/15 rounded-xl p-3.5 bg-white/5 backdrop-blur-sm hover:bg-white/10 transition-all duration-300 hover:border-blue-400/30">
                        <div class="text-xl font-bold text-white">98%</div>
                        <div class="text-[8px] text-slate-400 uppercase tracking-wider mt-0.5">Satisfaction</div>
                    </div>
                    <div class="text-center border border-white/15 rounded-xl p-3.5 bg-white/5 backdrop-blur-sm hover:bg-white/10 transition-all duration-300 hover:border-blue-400/30">
                        <div class="text-xl font-bold text-white">25+</div>
                        <div class="text-[8px] text-slate-400 uppercase tracking-wider mt-0.5">Years</div>
                    </div>
                </div>

                <section class="max-w-4xl mx-auto w-full mb-10 shrink-0">
                    <div class="bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-6 md:p-8">
                        <div class="grid md:grid-cols-2 gap-8 items-center">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-widest text-blue-400">About Monti Textile</span>
                                <h2 class="text-2xl font-bold text-white mt-2 mb-3">Leading the Textile Industry</h2>
                                <p class="text-sm text-slate-300 leading-relaxed">
                                    Monti Textile is a premier manufacturer of high-quality textile products, serving
                                    global markets with innovative solutions.
                                </p>
                                <ul class="mt-4 space-y-2">
                                    <li class="flex items-center text-sm text-slate-300">
                                        <svg class="w-4 h-4 text-blue-400 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        ISO 9001:2015 Certified
                                    </li>
                                    <li class="flex items-center text-sm text-slate-300">
                                        <svg class="w-4 h-4 text-blue-400 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Sustainable Manufacturing Practices
                                    </li>
                                    <li class="flex items-center text-sm text-slate-300">
                                        <svg class="w-4 h-4 text-blue-400 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Global Distribution Network
                                    </li>
                                </ul>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-white/5 border border-white/10 rounded-xl p-4 text-center">
                                    <div class="text-2xl font-bold text-blue-400">100+</div>
                                    <div class="text-[10px] text-slate-400 uppercase tracking-wider mt-1">Employees</div>
                                </div>
                                <div class="bg-white/5 border border-white/10 rounded-xl p-4 text-center">
                                    <div class="text-2xl font-bold text-blue-400">50+</div>
                                    <div class="text-[10px] text-slate-400 uppercase tracking-wider mt-1">Countries</div>
                                </div>
                                <div class="bg-white/5 border border-white/10 rounded-xl p-4 text-center">
                                    <div class="text-2xl font-bold text-blue-400">1M+</div>
                                    <div class="text-[10px] text-slate-400 uppercase tracking-wider mt-1">Units Produced</div>
                                </div>
                                <div class="bg-white/5 border border-white/10 rounded-xl p-4 text-center">
                                    <div class="text-2xl font-bold text-blue-400">98%</div>
                                    <div class="text-[10px] text-slate-400 uppercase tracking-wider mt-1">Client Retention</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="max-w-4xl mx-auto w-full mb-10 shrink-0">
                    <div class="text-center mb-6">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-blue-400">Why Choose Us</span>
                        <h2 class="text-2xl font-bold text-white mt-1">Excellence in Every Thread</h2>
                    </div>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 text-center hover:bg-white/10 transition-all hover:border-blue-400/30">
                            <div class="text-3xl mb-3">🏆</div>
                            <h4 class="text-sm font-bold text-white">Quality First</h4>
                            <p class="text-xs text-slate-400 mt-1">Uncompromising quality standards</p>
                        </div>
                        <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 text-center hover:bg-white/10 transition-all hover:border-blue-400/30">
                            <div class="text-3xl mb-3">🔬</div>
                            <h4 class="text-sm font-bold text-white">Innovation</h4>
                            <p class="text-xs text-slate-400 mt-1">Cutting-edge technology</p>
                        </div>
                        <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 text-center hover:bg-white/10 transition-all hover:border-blue-400/30">
                            <div class="text-3xl mb-3">🌱</div>
                            <h4 class="text-sm font-bold text-white">Sustainability</h4>
                            <p class="text-xs text-slate-400 mt-1">Eco-friendly practices</p>
                        </div>
                        <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 text-center hover:bg-white/10 transition-all hover:border-blue-400/30">
                            <div class="text-3xl mb-3">🤝</div>
                            <h4 class="text-sm font-bold text-white">Partnership</h4>
                            <p class="text-xs text-slate-400 mt-1">Long-term relationships</p>
                        </div>
                    </div>
                </section>

                <section class="max-w-4xl mx-auto w-full mb-10 shrink-0">
                    <div class="text-center mb-6">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-blue-400">Our Services</span>
                        <h2 class="text-2xl font-bold text-white mt-1">What We Offer</h2>
                    </div>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="service in services.slice(0, 3)" :key="service.title" class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 hover:bg-white/10 transition-all hover:border-blue-400/30">
                            <div class="text-2xl mb-2">{{ service.icon }}</div>
                            <h4 class="text-sm font-bold text-white">{{ service.title }}</h4>
                            <p class="text-xs text-slate-400 mt-1">{{ service.description }}</p>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <button @click="switchPage('services')" class="text-xs text-blue-400 hover:text-blue-300 font-semibold transition-colors">
                            View All Services →
                        </button>
                    </div>
                </section>

                <section class="max-w-4xl mx-auto w-full mb-8 shrink-0">
                    <div class="text-center mb-6">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-blue-400">Careers</span>
                        <h2 class="text-2xl font-bold text-white mt-1">Join Our Team</h2>
                    </div>
                    <div class="bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-6 divide-y divide-white/10">
                        <div v-for="job in jobs.slice(0, 3)" :key="job.id" class="py-4 first:pt-0 last:pb-0 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-bold text-white">{{ job.title }}</h4>
                                <div class="flex flex-wrap gap-3 text-xs text-slate-400 mt-1">
                                    <span>{{ job.department }}</span>
                                    <span>•</span>
                                    <span>{{ job.work_location || 'On-site' }}</span>
                                    <span>•</span>
                                    <span :class="getEmploymentTypeBadge(job.employment_type)" class="px-2 py-0.5 rounded-full border">
                                        {{ getEmploymentTypeIcon(job.employment_type) }} {{ job.employment_type }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-[10px] text-slate-400">{{ getPostedDisplay(job) }}</span>
                                <button @click="applyForJob(job)" class="text-xs font-semibold text-blue-400 hover:text-blue-300 transition-colors">
                                    Apply →
                                </button>
                            </div>
                        </div>
                        <div v-if="jobs.length === 0" class="py-8 text-center">
                            <p class="text-sm text-slate-400">No active job openings at the moment.</p>
                            <p class="text-xs text-slate-500 mt-1">Please check back later.</p>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <button @click="switchPage('careers')" class="text-xs text-blue-400 hover:text-blue-300 font-semibold transition-colors">
                            View All Openings →
                        </button>
                    </div>
                </section>

                <!-- Footer with Access Buttons -->
                <footer class="border-t border-white/10 py-6 mt-4 shrink-0">
                    <!-- Quick Access Row -->
                    <div class="flex flex-wrap justify-center gap-3 sm:gap-4 pb-4 mb-4 border-b border-white/10">
                        <Link :href="getSafeRoute('client.login', '/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Business Login
                        </Link>
                        <Link v-if="canRegister" :href="getSafeRoute('client.register', '/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Be our Partner
                        </Link>
                        <Link :href="getSafeRoute('apply', '/apply')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Send Application
                        </Link>
                        <Link :href="getSafeRoute('applicant.register', '/applicant/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Applicant Registration
                        </Link>
                        <Link :href="getSafeRoute('applicant.login', '/applicant/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Applicant Login
                        </Link>
                        <Link :href="getSafeRoute('supplier.register', '/supplier/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Register Business
                        </Link>
                        <Link :href="getSafeRoute('login', '/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Employee Portal
                        </Link>
                        <Link :href="getSafeRoute('supplier.login', '/supplier/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Supplier Portal
                        </Link>
                        <Link :href="getSafeRoute('agency.login', '/agency/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Agency Portal
                        </Link>
                    </div>

                    <div class="grid sm:grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Monti Textile</h5>
                            <p class="text-xs text-slate-400 leading-relaxed">Precision management for the textile manufacturing lifecycle.</p>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Quick Links</h5>
                            <ul class="space-y-1.5 text-xs">
                                <li><button @click="switchPage('about')" class="text-slate-400 hover:text-white transition-colors">About Us</button></li>
                                <li><button @click="switchPage('services')" class="text-slate-400 hover:text-white transition-colors">Services</button></li>
                                <li><button @click="switchPage('careers')" class="text-slate-400 hover:text-white transition-colors">Careers</button></li>
                                <li><button @click="switchPage('contact')" class="text-slate-400 hover:text-white transition-colors">Contact</button></li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Services</h5>
                            <ul class="space-y-1.5 text-xs">
                                <li class="text-slate-400">Software Development</li>
                                <li class="text-slate-400">Web Development</li>
                                <li class="text-slate-400">Cloud Solutions</li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Contact</h5>
                            <ul class="space-y-1.5 text-xs text-slate-400">
                                <li>📍 Makati, Philippines</li>
                                <li>📞 +63 2 8888 7777</li>
                                <li>📧 info@montitextile.com</li>
                            </ul>
                        </div>
                    </div>
                    <div class="border-t border-white/10 pt-4 text-center text-[8px] text-slate-500">
                        © 2026 Monti Textile Manufacturing. All rights reserved.
                    </div>
                </footer>
            </div>

            <!-- ABOUT PAGE -->
            <div v-if="activePage === 'about'" class="flex-1 flex flex-col py-4">
                <div class="max-w-4xl mx-auto w-full">
                    <div class="text-center mb-8">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-blue-400">About Us</span>
                        <h1 class="text-3xl sm:text-4xl font-bold text-white mt-2 mb-3">Our Story</h1>
                        <p class="text-sm text-slate-300 max-w-2xl mx-auto">Driving innovation in textile manufacturing since 2000.</p>
                    </div>

                    <div class="bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-6 md:p-8 mb-6">
                        <h2 class="text-xl font-bold text-white mb-3">Company Story</h2>
                        <p class="text-sm text-slate-300 leading-relaxed mb-4">
                            Founded in 2000, Monti Textile has grown from a small weaving operation to a global leader
                            in textile manufacturing. Our journey is defined by innovation, quality, and a commitment to
                            sustainable practices.
                        </p>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            Today, we serve clients across 50+ countries, producing over 1 million units annually.
                            Our state-of-the-art facilities and dedicated team ensure we deliver excellence in every product.
                        </p>
                    </div>

                    <div class="grid md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 text-center">
                            <div class="text-3xl mb-2">🎯</div>
                            <h4 class="text-sm font-bold text-white">Mission</h4>
                            <p class="text-xs text-slate-400 mt-1">To deliver innovative textile solutions that exceed expectations.</p>
                        </div>
                        <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 text-center">
                            <div class="text-3xl mb-2">👁️</div>
                            <h4 class="text-sm font-bold text-white">Vision</h4>
                            <p class="text-xs text-slate-400 mt-1">To be the global leader in sustainable textile manufacturing.</p>
                        </div>
                        <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 text-center">
                            <div class="text-3xl mb-2">💎</div>
                            <h4 class="text-sm font-bold text-white">Core Values</h4>
                            <p class="text-xs text-slate-400 mt-1">Innovation, Quality, Integrity, Sustainability.</p>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4 mb-6">
                        <div v-for="value in coreValues" :key="value.title" class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 hover:bg-white/10 transition-all">
                            <div class="text-2xl mb-2">{{ value.icon }}</div>
                            <h4 class="text-sm font-bold text-white">{{ value.title }}</h4>
                            <p class="text-xs text-slate-400 mt-1">{{ value.description }}</p>
                        </div>
                    </div>

                    <div class="bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-6">
                        <h2 class="text-xl font-bold text-white mb-4">Leadership Team</h2>
                        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-4">
                            <div v-for="leader in leadership" :key="leader.name" class="bg-white/5 border border-white/10 rounded-xl p-4 text-center hover:bg-white/10 transition-all">
                                <div class="w-16 h-16 rounded-full bg-blue-500/20 border border-blue-400/30 flex items-center justify-center text-white font-bold text-xl mx-auto mb-3">
                                    {{ leader.avatar }}
                                </div>
                                <h4 class="text-sm font-bold text-white">{{ leader.name }}</h4>
                                <p class="text-xs text-blue-400 font-semibold">{{ leader.role }}</p>
                                <p class="text-xs text-slate-400 mt-1">{{ leader.bio }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-6">
                        <button @click="switchPage('home')" class="text-sm text-slate-400 hover:text-white transition-colors">
                            ← Back to Home
                        </button>
                    </div>
                </div>

                <!-- Footer with Access Buttons -->
                <footer class="border-t border-white/10 py-6 mt-4 shrink-0">
                    <div class="flex flex-wrap justify-center gap-3 sm:gap-4 pb-4 mb-4 border-b border-white/10">
                        <Link :href="getSafeRoute('client.login', '/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Business Login
                        </Link>
                        <Link v-if="canRegister" :href="getSafeRoute('client.register', '/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Be our Partner
                        </Link>
                        <Link :href="getSafeRoute('apply', '/apply')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Send Application
                        </Link>
                        <Link :href="getSafeRoute('applicant.register', '/applicant/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Applicant Registration
                        </Link>
                        <Link :href="getSafeRoute('applicant.login', '/applicant/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Applicant Login
                        </Link>
                        <Link :href="getSafeRoute('supplier.register', '/supplier/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Register Business
                        </Link>
                        <Link :href="getSafeRoute('login', '/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Employee Portal
                        </Link>
                        <Link :href="getSafeRoute('supplier.login', '/supplier/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Supplier Portal
                        </Link>
                        <Link :href="getSafeRoute('agency.login', '/agency/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Agency Portal
                        </Link>
                    </div>
                    <div class="grid sm:grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Monti Textile</h5>
                            <p class="text-xs text-slate-400 leading-relaxed">Precision management for the textile manufacturing lifecycle.</p>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Quick Links</h5>
                            <ul class="space-y-1.5 text-xs">
                                <li><button @click="switchPage('about')" class="text-slate-400 hover:text-white transition-colors">About Us</button></li>
                                <li><button @click="switchPage('services')" class="text-slate-400 hover:text-white transition-colors">Services</button></li>
                                <li><button @click="switchPage('careers')" class="text-slate-400 hover:text-white transition-colors">Careers</button></li>
                                <li><button @click="switchPage('contact')" class="text-slate-400 hover:text-white transition-colors">Contact</button></li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Services</h5>
                            <ul class="space-y-1.5 text-xs">
                                <li class="text-slate-400">Software Development</li>
                                <li class="text-slate-400">Web Development</li>
                                <li class="text-slate-400">Cloud Solutions</li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Contact</h5>
                            <ul class="space-y-1.5 text-xs text-slate-400">
                                <li>📍 Makati, Philippines</li>
                                <li>📞 +63 2 8888 7777</li>
                                <li>📧 info@montitextile.com</li>
                            </ul>
                        </div>
                    </div>
                    <div class="border-t border-white/10 pt-4 text-center text-[8px] text-slate-500">
                        © 2026 Monti Textile Manufacturing. All rights reserved.
                    </div>
                </footer>
            </div>

            <!-- SERVICES PAGE -->
            <div v-if="activePage === 'services'" class="flex-1 flex flex-col py-4">
                <div class="max-w-4xl mx-auto w-full">
                    <div class="text-center mb-8">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-blue-400">Our Services</span>
                        <h1 class="text-3xl sm:text-4xl font-bold text-white mt-2 mb-3">What We Offer</h1>
                        <p class="text-sm text-slate-300 max-w-2xl mx-auto">Comprehensive technology solutions for your business.</p>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4 mb-6">
                        <div v-for="service in services" :key="service.title" class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-6 hover:bg-white/10 transition-all hover:border-blue-400/30">
                            <div class="text-3xl mb-3">{{ service.icon }}</div>
                            <h4 class="text-lg font-bold text-white">{{ service.title }}</h4>
                            <p class="text-sm text-slate-300 mt-1">{{ service.description }}</p>
                            <ul class="mt-3 space-y-1">
                                <li v-for="feature in service.features" :key="feature" class="text-xs text-slate-400 flex items-center">
                                    <svg class="w-3 h-3 text-blue-400 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ feature }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="text-center mt-6">
                        <button @click="switchPage('home')" class="text-sm text-slate-400 hover:text-white transition-colors">
                            ← Back to Home
                        </button>
                    </div>
                </div>

                <!-- Footer with Access Buttons -->
                <footer class="border-t border-white/10 py-6 mt-4 shrink-0">
                    <div class="flex flex-wrap justify-center gap-3 sm:gap-4 pb-4 mb-4 border-b border-white/10">
                        <Link :href="getSafeRoute('client.login', '/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Business Login
                        </Link>
                        <Link v-if="canRegister" :href="getSafeRoute('client.register', '/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Be our Partner
                        </Link>
                        <Link :href="getSafeRoute('apply', '/apply')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Send Application
                        </Link>
                        <Link :href="getSafeRoute('applicant.register', '/applicant/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Applicant Registration
                        </Link>
                        <Link :href="getSafeRoute('applicant.login', '/applicant/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Applicant Login
                        </Link>
                        <Link :href="getSafeRoute('supplier.register', '/supplier/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Register Business
                        </Link>
                        <Link :href="getSafeRoute('login', '/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Employee Portal
                        </Link>
                        <Link :href="getSafeRoute('supplier.login', '/supplier/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Supplier Portal
                        </Link>
                        <Link :href="getSafeRoute('agency.login', '/agency/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Agency Portal
                        </Link>
                    </div>
                    <div class="grid sm:grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Monti Textile</h5>
                            <p class="text-xs text-slate-400 leading-relaxed">Precision management for the textile manufacturing lifecycle.</p>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Quick Links</h5>
                            <ul class="space-y-1.5 text-xs">
                                <li><button @click="switchPage('about')" class="text-slate-400 hover:text-white transition-colors">About Us</button></li>
                                <li><button @click="switchPage('services')" class="text-slate-400 hover:text-white transition-colors">Services</button></li>
                                <li><button @click="switchPage('careers')" class="text-slate-400 hover:text-white transition-colors">Careers</button></li>
                                <li><button @click="switchPage('contact')" class="text-slate-400 hover:text-white transition-colors">Contact</button></li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Services</h5>
                            <ul class="space-y-1.5 text-xs">
                                <li class="text-slate-400">Software Development</li>
                                <li class="text-slate-400">Web Development</li>
                                <li class="text-slate-400">Cloud Solutions</li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Contact</h5>
                            <ul class="space-y-1.5 text-xs text-slate-400">
                                <li>📍 Makati, Philippines</li>
                                <li>📞 +63 2 8888 7777</li>
                                <li>📧 info@montitextile.com</li>
                            </ul>
                        </div>
                    </div>
                    <div class="border-t border-white/10 pt-4 text-center text-[8px] text-slate-500">
                        © 2026 Monti Textile Manufacturing. All rights reserved.
                    </div>
                </footer>
            </div>

            <!-- CAREERS PAGE -->
            <div v-if="activePage === 'careers'" class="flex-1 flex flex-col py-4">
                <div class="max-w-4xl mx-auto w-full">
                    <div class="text-center mb-6">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-blue-400">Careers</span>
                        <h1 class="text-3xl sm:text-4xl font-bold text-white mt-2 mb-3">Join Our Team</h1>
                        <p class="text-sm text-slate-300 max-w-2xl mx-auto">Build your career with Monti Textile and shape the future of manufacturing.</p>
                    </div>

                    <!-- Search & Filters -->
                    <div class="bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-5 mb-6">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-4 border-b border-white/10">
                            <p class="text-xs text-slate-300">
                                Already applied? Track your hiring progress in the applicant portal.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <Link :href="getSafeRoute('applicant.login', '/applicant/login')"
                                    class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition-all active:scale-95">
                                    Applicant Login
                                </Link>
                                <Link :href="getSafeRoute('applicant.register', '/applicant/register')"
                                    class="px-4 py-1.5 bg-white/10 backdrop-blur-md border border-white/20 text-white rounded-lg text-xs font-bold hover:bg-white/20 transition-all active:scale-95">
                                    Create Account
                                </Link>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <div class="flex-1 min-w-[200px]">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search jobs by title, department, skills, or employment type..."
                                    class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                />
                            </div>
                            <div>
                                <select
                                    v-model="filterType"
                                    class="px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-400"
                                >
                                    <option value="" class="bg-slate-800">All Types</option>
                                    <option v-for="type in employmentTypes" :key="type" :value="type" class="bg-slate-800">
                                        {{ type }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="searchQuery || filterType" class="flex items-center">
                                <button @click="searchQuery = ''; filterType = ''" class="text-xs text-slate-400 hover:text-white transition-colors">
                                    Clear Filters
                                </button>
                            </div>
                        </div>
                        <div class="text-xs text-slate-400 mt-3">
                            Found {{ filteredJobs.length }} {{ filteredJobs.length === 1 ? 'job' : 'jobs' }}
                        </div>
                    </div>

                    <!-- Job Cards -->
                    <div class="space-y-4 mb-6">
                        <div v-for="job in filteredJobs" :key="job.id" 
                             class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 hover:bg-white/10 transition-all hover:border-blue-400/30 cursor-pointer"
                             @click="viewJobDetail(job)">
                            <div class="flex flex-wrap justify-between items-start gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="text-lg font-bold text-white">{{ job.title }}</h4>
                                        <span v-if="job.is_urgent" class="px-2 py-0.5 bg-rose-500/20 text-rose-400 rounded-full text-[10px] font-bold border border-rose-400/30 animate-pulse">
                                            🔴 URGENT
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-xs text-slate-400 mt-1">
                                        <span class="px-2 py-0.5 rounded-full border border-slate-400/20">{{ job.department }}</span>
                                        <span>•</span>
                                        <span>{{ job.work_mode || 'On-site' }} {{ getWorkModeIcon(job.work_mode) }}</span>
                                        <span>•</span>
                                        <span :class="getEmploymentTypeBadge(job.employment_type)" class="px-2 py-0.5 rounded-full border">
                                            {{ getEmploymentTypeIcon(job.employment_type) }} {{ job.employment_type }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-slate-300 mt-2 line-clamp-2">{{ job.job_description }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-[10px] text-slate-400 block">{{ getPostedDisplay(job) }}</span>
                                    <button 
                                        @click.stop="applyForJob(job)" 
                                        class="mt-2 px-4 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold transition-all active:scale-95"
                                    >
                                        Apply Now
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-if="filteredJobs.length === 0" class="text-center py-8 text-slate-400">
                            <p class="text-sm">No jobs found matching your criteria.</p>
                            <button @click="searchQuery = ''; filterType = ''" class="text-xs text-blue-400 hover:text-blue-300 mt-2">Clear filters</button>
                        </div>
                    </div>

                    <div class="text-center mt-6">
                        <button @click="switchPage('home')" class="text-sm text-slate-400 hover:text-white transition-colors">
                            ← Back to Home
                        </button>
                    </div>
                </div>

                <!-- Footer with Access Buttons -->
                <footer class="border-t border-white/10 py-6 mt-4 shrink-0">
                    <div class="flex flex-wrap justify-center gap-3 sm:gap-4 pb-4 mb-4 border-b border-white/10">
                        <Link :href="getSafeRoute('client.login', '/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Business Login
                        </Link>
                        <Link v-if="canRegister" :href="getSafeRoute('client.register', '/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Be our Partner
                        </Link>
                        <Link :href="getSafeRoute('apply', '/apply')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Send Application
                        </Link>
                        <Link :href="getSafeRoute('applicant.register', '/applicant/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Applicant Registration
                        </Link>
                        <Link :href="getSafeRoute('applicant.login', '/applicant/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Applicant Login
                        </Link>
                        <Link :href="getSafeRoute('supplier.register', '/supplier/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Register Business
                        </Link>
                        <Link :href="getSafeRoute('login', '/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Employee Portal
                        </Link>
                        <Link :href="getSafeRoute('supplier.login', '/supplier/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Supplier Portal
                        </Link>
                        <Link :href="getSafeRoute('agency.login', '/agency/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Agency Portal
                        </Link>
                    </div>
                    <div class="grid sm:grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Monti Textile</h5>
                            <p class="text-xs text-slate-400 leading-relaxed">Precision management for the textile manufacturing lifecycle.</p>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Quick Links</h5>
                            <ul class="space-y-1.5 text-xs">
                                <li><button @click="switchPage('about')" class="text-slate-400 hover:text-white transition-colors">About Us</button></li>
                                <li><button @click="switchPage('services')" class="text-slate-400 hover:text-white transition-colors">Services</button></li>
                                <li><button @click="switchPage('careers')" class="text-slate-400 hover:text-white transition-colors">Careers</button></li>
                                <li><button @click="switchPage('contact')" class="text-slate-400 hover:text-white transition-colors">Contact</button></li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Services</h5>
                            <ul class="space-y-1.5 text-xs">
                                <li class="text-slate-400">Software Development</li>
                                <li class="text-slate-400">Web Development</li>
                                <li class="text-slate-400">Cloud Solutions</li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Contact</h5>
                            <ul class="space-y-1.5 text-xs text-slate-400">
                                <li>📍 Makati, Philippines</li>
                                <li>📞 +63 2 8888 7777</li>
                                <li>📧 info@montitextile.com</li>
                            </ul>
                        </div>
                    </div>
                    <div class="border-t border-white/10 pt-4 text-center text-[8px] text-slate-500">
                        © 2026 Monti Textile Manufacturing. All rights reserved.
                    </div>
                </footer>
            </div>

            <!-- CONTACT PAGE -->
            <div v-if="activePage === 'contact'" class="flex-1 flex flex-col py-4">
                <div class="max-w-4xl mx-auto w-full">
                    <div class="text-center mb-6">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-blue-400">Contact Us</span>
                        <h1 class="text-3xl sm:text-4xl font-bold text-white mt-2 mb-3">Get In Touch</h1>
                        <p class="text-sm text-slate-300 max-w-2xl mx-auto">We'd love to hear from you. Reach out to us anytime.</p>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <div class="bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-6 h-full">
                                <h4 class="text-lg font-bold text-white mb-4">Contact Information</h4>
                                <div class="space-y-4">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-blue-500/20 rounded-lg text-blue-400">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-400">Location</p>
                                            <p class="text-sm text-white font-medium">Makati, Philippines</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-blue-500/20 rounded-lg text-blue-400">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-400">Phone</p>
                                            <p class="text-sm text-white font-medium">+63 2 8888 7777</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-blue-500/20 rounded-lg text-blue-400">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-400">Email</p>
                                            <p class="text-sm text-white font-medium">info@montitextile.com</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-6">
                                <h4 class="text-lg font-bold text-white mb-4">Send a Message</h4>
                                <form class="space-y-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Full Name</label>
                                        <input type="text" class="w-full px-4 py-2.5 bg-white/10 border border-white/20 rounded-lg text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400" placeholder="John Doe" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Email Address</label>
                                        <input type="email" class="w-full px-4 py-2.5 bg-white/10 border border-white/20 rounded-lg text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400" placeholder="john@example.com" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Message</label>
                                        <textarea rows="4" class="w-full px-4 py-2.5 bg-white/10 border border-white/20 rounded-lg text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none" placeholder="Your message here..."></textarea>
                                    </div>
                                    <button type="submit" class="w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-sm font-bold transition-all shadow-lg shadow-blue-600/30 active:scale-95">
                                        Send Message
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-6">
                        <button @click="switchPage('home')" class="text-sm text-slate-400 hover:text-white transition-colors">
                            ← Back to Home
                        </button>
                    </div>
                </div>

                <!-- Footer with Access Buttons -->
                <footer class="border-t border-white/10 py-6 mt-4 shrink-0">
                    <div class="flex flex-wrap justify-center gap-3 sm:gap-4 pb-4 mb-4 border-b border-white/10">
                        <Link :href="getSafeRoute('client.login', '/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Business Login
                        </Link>
                        <Link v-if="canRegister" :href="getSafeRoute('client.register', '/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Be our Partner
                        </Link>
                        <Link :href="getSafeRoute('apply', '/apply')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Send Application
                        </Link>
                        <Link :href="getSafeRoute('applicant.register', '/applicant/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Applicant Registration
                        </Link>
                        <Link :href="getSafeRoute('applicant.login', '/applicant/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Applicant Login
                        </Link>
                        <Link :href="getSafeRoute('supplier.register', '/supplier/register')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Register Business
                        </Link>
                        <Link :href="getSafeRoute('login', '/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Employee Portal
                        </Link>
                        <Link :href="getSafeRoute('supplier.login', '/supplier/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Supplier Portal
                        </Link>
                        <Link :href="getSafeRoute('agency.login', '/agency/login')" class="text-[10px] font-bold text-slate-300 hover:text-white transition-colors uppercase tracking-widest">
                            Agency Portal
                        </Link>
                    </div>
                    <div class="grid sm:grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Monti Textile</h5>
                            <p class="text-xs text-slate-400 leading-relaxed">Precision management for the textile manufacturing lifecycle.</p>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Quick Links</h5>
                            <ul class="space-y-1.5 text-xs">
                                <li><button @click="switchPage('about')" class="text-slate-400 hover:text-white transition-colors">About Us</button></li>
                                <li><button @click="switchPage('services')" class="text-slate-400 hover:text-white transition-colors">Services</button></li>
                                <li><button @click="switchPage('careers')" class="text-slate-400 hover:text-white transition-colors">Careers</button></li>
                                <li><button @click="switchPage('contact')" class="text-slate-400 hover:text-white transition-colors">Contact</button></li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Services</h5>
                            <ul class="space-y-1.5 text-xs">
                                <li class="text-slate-400">Software Development</li>
                                <li class="text-slate-400">Web Development</li>
                                <li class="text-slate-400">Cloud Solutions</li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-white mb-3">Contact</h5>
                            <ul class="space-y-1.5 text-xs text-slate-400">
                                <li>📍 Makati, Philippines</li>
                                <li>📞 +63 2 8888 7777</li>
                                <li>📧 info@montitextile.com</li>
                            </ul>
                        </div>
                    </div>
                    <div class="border-t border-white/10 pt-4 text-center text-[8px] text-slate-500">
                        © 2026 Monti Textile Manufacturing. All rights reserved.
                    </div>
                </footer>
            </div>

            <!-- JOB DETAIL MODAL -->
            <div v-if="showJobDetail && selectedJob" 
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
                 @click.self="closeJobDetail">
                <div class="bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl">
                    
                    <!-- Modal Header -->
                    <div class="sticky top-0 bg-black/50 backdrop-blur-md border-b border-white/10 p-6 flex items-center justify-between rounded-t-2xl">
                        <div>
                            <h2 class="text-xl font-bold text-white">{{ selectedJob.title }}</h2>
                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                <span class="text-xs text-slate-300">{{ selectedJob.department }}</span>
                                <span class="text-xs text-slate-400">•</span>
                                <span class="text-xs text-slate-300">{{ selectedJob.work_location || 'On-site' }}</span>
                                <span class="text-xs text-slate-400">•</span>
                                <span :class="getEmploymentTypeBadge(selectedJob.employment_type)" class="px-2 py-0.5 rounded-full text-[10px] border">
                                    {{ getEmploymentTypeIcon(selectedJob.employment_type) }} {{ selectedJob.employment_type }}
                                </span>
                                <span v-if="selectedJob.is_urgent" class="px-2 py-0.5 bg-rose-500/20 text-rose-400 rounded-full text-[10px] font-bold border border-rose-400/30 animate-pulse">
                                    🔴 URGENT
                                </span>
                            </div>
                        </div>
                        <button @click="closeJobDetail" class="p-2 hover:bg-white/10 rounded-xl text-slate-400 hover:text-white transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-6">
                        
                        <!-- Posted Date & Employment Type -->
                        <div class="flex flex-wrap justify-between items-center text-xs text-slate-400">
                            <span>Posted: <span class="text-white">{{ getPostedDisplay(selectedJob) }}</span></span>
                            <span v-if="selectedJob.closing_date">Deadline: <span class="text-amber-400">{{ selectedJob.closing_date }}</span></span>
                        </div>

                        <!-- Employment Type Badge (Prominent) -->
                        <div class="bg-white/5 rounded-xl p-4 border border-white/10 flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Employment Type</h4>
                                <p class="text-white font-medium text-lg">{{ selectedJob.employment_type }}</p>
                            </div>
                            <span :class="getEmploymentTypeBadge(selectedJob.employment_type)" class="px-4 py-2 rounded-full text-sm border">
                                {{ getEmploymentTypeIcon(selectedJob.employment_type) }} {{ selectedJob.employment_type }}
                            </span>
                        </div>

                        <!-- Position Name -->
                        <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Position</h4>
                            <p class="text-white font-medium">{{ selectedJob.position_name }}</p>
                        </div>

                        <!-- Job Description -->
                        <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Job Description</h4>
                            <p class="text-sm text-slate-300 leading-relaxed">{{ selectedJob.job_description }}</p>
                        </div>

                        <!-- Responsibilities -->
                        <div class="bg-white/5 rounded-xl p-4 border border-white/10" v-if="selectedJob.responsibilities">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Key Responsibilities</h4>
                            <div class="text-sm text-slate-300 leading-relaxed whitespace-pre-wrap">{{ selectedJob.responsibilities }}</div>
                        </div>

                        <!-- Qualifications -->
                        <div class="bg-white/5 rounded-xl p-4 border border-white/10" v-if="selectedJob.qualifications">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Qualifications</h4>
                            <div class="text-sm text-slate-300 leading-relaxed whitespace-pre-wrap">{{ selectedJob.qualifications }}</div>
                        </div>

                        <!-- Required Skills -->
                        <div class="bg-white/5 rounded-xl p-4 border border-white/10" v-if="selectedJob.required_skills">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Required Skills</h4>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="skill in selectedJob.required_skills.split(',').map((s: string) => s.trim())" 
                                      :key="skill"
                                      class="px-3 py-1 bg-blue-500/20 text-blue-300 rounded-full text-xs border border-blue-400/20">
                                    {{ skill }}
                                </span>
                            </div>
                        </div>

                        <!-- Work Details -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Work Mode</h4>
                                <p class="text-white">{{ selectedJob.work_mode || 'On-site' }}</p>
                            </div>
                            <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Work Schedule</h4>
                                <p class="text-white">{{ selectedJob.work_schedule || 'Standard' }}</p>
                            </div>
                        </div>

                        <!-- Salary -->
                        <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Salary</h4>
                            <p class="text-lg font-bold text-emerald-400">
                                {{ formatSalary(selectedJob.salary_min, selectedJob.salary_max, selectedJob.salary_visibility) }}
                            </p>
                        </div>

                        <!-- Apply Button -->
                        <button 
                            @click="applyForJob(selectedJob)" 
                            class="w-full py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5"
                        >
                            Apply Now
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&display=swap');

.font-mono {
    font-family: 'JetBrains Mono', monospace;
}

html {
    scroll-behavior: smooth;
}

::-webkit-scrollbar {
    width: 6px;
}
::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}
::-webkit-scrollbar-thumb {
    background: rgba(59, 130, 246, 0.5);
    border-radius: 3px;
}
::-webkit-scrollbar-thumb:hover {
    background: rgba(59, 130, 246, 0.7);
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>