<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch, type Directive } from 'vue';
import { router } from '@inertiajs/vue3';
import { initTheme } from '@/composables/useTheme';

const props = defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
    companyPosts?: Array<{ id: number; caption: string; media: Array<{ url: string; type: string }>; created_at: string | null; creator: string }>;
    products?: Array<{ id: number; name: string; category: string; images: Array<{ url: string }> }>;
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
const page = usePage();

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
const productImageIndexes = ref<Record<number, number>>({});
const postMediaIndexes = ref<Record<number, number>>({});
let productCarouselInterval: number | null = null;

// Hero search bar (filters the public product catalog below)
const productSearch = ref('');
const productCategory = ref('');
const mobileOpen = ref(false);
const navWrapRef = ref<HTMLElement | null>(null);

const navItems = [
    { id: 'home', label: 'Home' },
    { id: 'about', label: 'About' },
    { id: 'services', label: 'Services' },
    { id: 'careers', label: 'Careers' },
    { id: 'contact', label: 'Contact' },
];

const productCategories = computed(() => {
    const set = new Set<string>();
    (props.products || []).forEach(p => {
        if (p.category && p.category.toLowerCase() !== 'uncategorized') set.add(p.category);
    });
    return Array.from(set).sort();
});

const filteredProducts = computed(() => {
    const q = productSearch.value.trim().toLowerCase();
    return (props.products || []).filter(p =>
        (!q || p.name.toLowerCase().includes(q) || (p.category || '').toLowerCase().includes(q)) &&
        (!productCategory.value || p.category === productCategory.value)
    );
});

const runHeroSearch = () => {
    document.getElementById('products')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const currentProductImage = (product: any) => {
    if (!product.images?.length) return null;
    const index = productImageIndexes.value[product.id] || 0;
    return product.images[index % product.images.length];
};

// Preload the next image before swapping so the browser never paints an
// empty frame while a new carousel image decodes.
const preloadUrl = (url?: string) => {
    if (!url || typeof Image === 'undefined') return;
    const img = new Image();
    img.decoding = 'async';
    img.src = url;
};

const changeProductImage = (product: any, step: number) => {
    const count = product.images?.length || 0;
    if (count < 2) return;
    const current = productImageIndexes.value[product.id] || 0;
    const next = (current + step + count) % count;
    preloadUrl(product.images[next]?.url);
    productImageIndexes.value[product.id] = next;
};

const currentPostMedia = (post: any) => {
    if (!post.media?.length) return null;
    const index = postMediaIndexes.value[post.id] || 0;
    return post.media[index % post.media.length];
};

const changePostMedia = (post: any, step: number) => {
    const count = post.media?.length || 0;
    if (count < 2) return;
    const current = postMediaIndexes.value[post.id] || 0;
    const next = (current + step + count) % count;
    preloadUrl(post.media[next]?.url);
    postMediaIndexes.value[post.id] = next;
};

// ---------------------------------------------------------------------------
// Motion helpers: scroll-reveal + count-up directives and a scroll progress bar
// ---------------------------------------------------------------------------
const prefersReducedMotion = (): boolean =>
    typeof window !== 'undefined' &&
    !!window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const REVEAL_CLASSES = ['reveal', 'reveal-up', 'reveal-left', 'reveal-right', 'reveal-zoom', 'is-in'];
let revealObserver: IntersectionObserver | null = null;

const getRevealObserver = (): IntersectionObserver => {
    if (!revealObserver) {
        revealObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    const el = entry.target as HTMLElement;
                    el.classList.add('is-in');
                    revealObserver?.unobserve(el);
                    // Clean up once the entrance finishes so hover transitions stay snappy.
                    const delay = parseInt(el.style.getPropertyValue('--rd') || '0', 10) || 0;
                    window.setTimeout(() => {
                        el.classList.remove(...REVEAL_CLASSES);
                        el.style.removeProperty('--rd');
                    }, delay + 1000);
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -6% 0px' },
        );
    }
    return revealObserver;
};

// Usage: v-reveal  |  v-reveal:left="120"  (arg = direction, value = delay in ms)
const vReveal: Directive<HTMLElement, number | undefined> = {
    mounted(el, binding) {
        if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') return;
        const type = binding.arg || 'up';
        el.classList.add('reveal', `reveal-${type}`);
        if (binding.value) el.style.setProperty('--rd', `${binding.value}ms`);
        getRevealObserver().observe(el);
    },
    unmounted(el) {
        revealObserver?.unobserve(el);
    },
};

// Usage: v-countup on an element whose text is like "10K+", "98%", "1M+"
const vCountup: Directive<HTMLElement> = {
    mounted(el) {
        if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') return;
        const original = el.textContent || '';
        const match = original.trim().match(/^(\D*)([\d.,]+)(.*)$/);
        if (!match) return;
        const [, prefix, numStr, suffix] = match;
        const target = parseFloat(numStr.replace(/,/g, ''));
        if (!isFinite(target)) return;

        el.textContent = `${prefix}0${suffix}`;
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (!entry.isIntersecting) return;
                observer.disconnect();
                const start = performance.now();
                const duration = 1700;
                const tick = (now: number) => {
                    const t = Math.min(1, (now - start) / duration);
                    const eased = 1 - Math.pow(1 - t, 3);
                    el.textContent = `${prefix}${Math.round(target * eased)}${suffix}`;
                    if (t < 1) requestAnimationFrame(tick);
                    else el.textContent = original;
                };
                requestAnimationFrame(tick);
            },
            { threshold: 0.6 },
        );
        observer.observe(el);
    },
};

// Tracks which company-post cards are on screen so we only advance media the
// visitor can actually see (avoids re-rendering dozens of offscreen images).
const visiblePostIds = new Set<number>();
let postObserver: IntersectionObserver | null = null;

const getPostObserver = (): IntersectionObserver => {
    if (!postObserver) {
        postObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    const id = Number((entry.target as HTMLElement).dataset.postId);
                    if (!id) return;
                    if (entry.isIntersecting) visiblePostIds.add(id);
                    else visiblePostIds.delete(id);
                });
            },
            { threshold: 0.15 },
        );
    }
    return postObserver;
};

// Usage: v-post-visible="post.id"
const vPostVisible: Directive<HTMLElement, number> = {
    mounted(el, binding) {
        el.dataset.postId = String(binding.value);
        if (typeof IntersectionObserver === 'undefined') {
            visiblePostIds.add(binding.value);
            return;
        }
        getPostObserver().observe(el);
    },
    unmounted(el) {
        postObserver?.unobserve(el);
    },
};

// The progress bar is updated straight through the DOM (rAF-throttled) rather
// than a reactive ref. Re-rendering this large template on every scroll event
// was a major source of jank while many images were on screen.
const progressBar = ref<HTMLElement | null>(null);
let scrollTicking = false;
const onScroll = () => {
    if (scrollTicking) return;
    scrollTicking = true;
    requestAnimationFrame(() => {
        scrollTicking = false;
        const max = document.documentElement.scrollHeight - window.innerHeight;
        const progress = max > 0 ? Math.min(1, Math.max(0, window.scrollY / max)) : 0;
        if (progressBar.value) progressBar.value.style.transform = `scaleX(${progress})`;
    });
};

// ---------------------------------------------------------------------------
// Products coverflow carousel
// ---------------------------------------------------------------------------
const productIndex = ref(0);
const productPaused = ref(false);
let coverInterval: number | null = null;
let dragStartX: number | null = null;
let dragMoved = false;

const productCount = computed(() => filteredProducts.value.length);

watch([productSearch, productCategory], () => {
    productIndex.value = 0;
});

const nextProduct = () => {
    if (productCount.value < 2) return;
    productIndex.value = (productIndex.value + 1) % productCount.value;
};
const prevProduct = () => {
    if (productCount.value < 2) return;
    productIndex.value = (productIndex.value - 1 + productCount.value) % productCount.value;
};

// Signed, wrap-around distance of card i from the centered card
const coverOffset = (i: number): number => {
    const n = productCount.value;
    if (!n) return 0;
    const active = productIndex.value % n;
    let d = (((i - active) % n) + n) % n;
    if (d > n / 2) d -= n;
    return d;
};

// Only keep a small window of cards around the active one in the DOM. With a
// large catalog this prevents hundreds of absolutely-positioned <img> cards
// (and their transitions) from thrashing the compositor.
const visibleProducts = computed(() => {
    const list = filteredProducts.value;
    const n = list.length;
    if (!n) return [];
    const active = productIndex.value % n;
    const result: Array<{ product: (typeof list)[number]; i: number }> = [];
    const seen = new Set<number>();
    // +/-3 keeps the two visible edge cards while the third is the hidden
    // buffer, so removing/adding cards never produces a visible pop.
    for (let d = -3; d <= 3; d++) {
        const i = (((active + d) % n) + n) % n;
        if (seen.has(i)) continue;
        seen.add(i);
        result.push({ product: list[i], i });
    }
    return result;
});

const COVER_X = [0, 0.74, 1.32, 1.8];
const COVER_SCALE = [1, 0.86, 0.74, 0.62];

const coverStyle = (i: number) => {
    const d = coverOffset(i);
    const a = Math.abs(d);
    const k = Math.min(a, 3);
    return {
        transform: `translateX(calc(var(--card-w) * ${Math.sign(d) * COVER_X[k]})) scale(${COVER_SCALE[k]})`,
        zIndex: 10 - a,
        opacity: a > 2 ? 0 : 1,
        pointerEvents: a > 2 ? 'none' : 'auto',
    } as Record<string, string | number>;
};

const onCoverPointerDown = (e: PointerEvent) => {
    dragStartX = e.clientX;
    dragMoved = false;
};
const onCoverPointerUp = (e: PointerEvent) => {
    if (dragStartX === null) return;
    const dx = e.clientX - dragStartX;
    dragStartX = null;
    if (Math.abs(dx) > 45) {
        dragMoved = true;
        if (dx < 0) nextProduct();
        else prevProduct();
    }
};
const onCoverPointerCancel = () => {
    dragStartX = null;
};
const onCoverClick = (i: number) => {
    if (dragMoved) {
        dragMoved = false;
        return;
    }
    if (coverOffset(i) !== 0) productIndex.value = i;
};

const postInitials = (name?: string): string =>
    (name || 'Monti Textile')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

// Update time every minute
let timeInterval: number | null = null;

onMounted(() => {
    // Stored user theme wins; otherwise fall back to the OS/browser theme.
    initTheme(page.props.themeSettings);
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleKeydown);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    // Initial load
    if (props.careerJobs) {
        jobs.value = props.careerJobs;
    }

    // Start timer to update relative times
    timeInterval = window.setInterval(() => {
        currentTime.value = new Date();
    }, 60000); // Update every minute

    productCarouselInterval = window.setInterval(() => {
        if (typeof document !== 'undefined' && document.hidden) return;

        // Advance only the centered card's image instead of every product at
        // once. Rotating the whole catalog on one tick caused a repaint storm
        // (and visible flicker) whenever there were many products.
        const list = filteredProducts.value;
        if (list.length) {
            const active = list[productIndex.value % list.length];
            if (active?.images?.length > 1) changeProductImage(active, 1);
        }

        // Advance media only for company posts currently on screen.
        (props.companyPosts || []).forEach(post => {
            if (visiblePostIds.has(post.id) && post.media?.length > 1) changePostMedia(post, 1);
        });
    }, 4500);

    // Coverflow autoplay (pauses while the visitor hovers, focuses or drags)
    if (!prefersReducedMotion()) {
        coverInterval = window.setInterval(() => {
            if (!productPaused.value) nextProduct();
        }, 5200);
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('scroll', onScroll);
    if (coverInterval) clearInterval(coverInterval);
    revealObserver?.disconnect();
    revealObserver = null;
    postObserver?.disconnect();
    postObserver = null;
    visiblePostIds.clear();
    if (timeInterval) {
        clearInterval(timeInterval);
    }
    if (productCarouselInterval) clearInterval(productCarouselInterval);
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

// Job applications go through the Applicant Portal (register/login, then apply from Jobs)
const applyForJob = (job: any) => {
    const positionName = job.position_name || job.title;
    router.visit('/applicant/register?position=' + encodeURIComponent(positionName));
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
    closeMenus();
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

// Light-theme badge colours (full class strings so Tailwind can detect them)
const getEmploymentTypeBadge = (type: string): string => {
    const map: Record<string, string> = {
        'Full-time': 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'Part-time': 'bg-sky-50 text-sky-800 border-sky-200',
        'Contract': 'bg-amber-50 text-amber-900 border-amber-200',
        'Temporary': 'bg-purple-50 text-purple-800 border-purple-200',
        'Internship': 'bg-rose-50 text-rose-800 border-rose-200',
        'Freelance': 'bg-indigo-50 text-indigo-800 border-indigo-200',
    };
    return map[type] || 'bg-stone-100 text-stone-700 border-stone-200';
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
        tag: 'Build',
        path: 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5',
        title: 'Software Development',
        description: 'Custom enterprise software solutions tailored to your business needs.',
        features: ['ERP Systems', 'Manufacturing Software', 'Custom Applications']
    },
    {
        tag: 'Web',
        path: 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418',
        title: 'Web Development',
        description: 'Modern, responsive web applications with cutting-edge technology stacks.',
        features: ['React/Vue.js', 'Laravel/PHP', 'Responsive Design']
    },
    {
        tag: 'Mobile',
        path: 'M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3',
        title: 'Mobile Apps',
        description: 'Native and cross-platform mobile applications for iOS and Android.',
        features: ['React Native', 'Flutter', 'iOS/Android']
    },
    {
        tag: 'Cloud',
        path: 'M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z',
        title: 'Cloud Solutions',
        description: 'Scalable cloud infrastructure and migration services.',
        features: ['AWS/Azure/GCP', 'Cloud Migration', 'DevOps']
    },
    {
        tag: 'Advisory',
        path: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        title: 'IT Consulting',
        description: 'Strategic technology consulting to drive digital transformation.',
        features: ['Strategy', 'Digital Transformation', 'IT Advisory']
    }
];

// "Learn More" on a service card takes the visitor to the contact page
const learnMore = (_service?: unknown) => switchPage('contact');

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
// ---------------------------------------------------------------------------
// Login / Register dropdown menus (replaces the many separate buttons/links)
// ---------------------------------------------------------------------------
const openMenu = ref<string | null>(null);

// Icon per account type
const menuIcons: Record<string, string> = {
    client: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
    employee: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
    supplier: 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
    applicant: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    agency: 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
};

// One soft orange tone for every account type (full class strings so Tailwind can detect them)
const menuTones: Record<string, string> = {
    client: 'bg-orange-50 text-orange-700 ring-orange-100 group-hover:bg-orange-100',
    employee: 'bg-orange-50 text-orange-700 ring-orange-100 group-hover:bg-orange-100',
    supplier: 'bg-orange-50 text-orange-700 ring-orange-100 group-hover:bg-orange-100',
    applicant: 'bg-orange-50 text-orange-700 ring-orange-100 group-hover:bg-orange-100',
    agency: 'bg-orange-50 text-orange-700 ring-orange-100 group-hover:bg-orange-100',
};

// Trigger-button glyphs: login arrow / user-plus
const menuGlyphs: Record<string, string> = {
    login: 'M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75',
    register: 'M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z',
};

const authMenus = computed(() => [
    {
        id: 'register',
        label: 'Register',
        heading: 'Create an account',
        subheading: 'Pick the account type that fits you',
        glyph: 'register',
        items: [
            ...(props.canRegister
                ? [{ label: 'Business Client', description: 'Be our Partner', kind: 'client', href: getSafeRoute('client.register', '/register') }]
                : []),
            { label: 'Supplier', description: 'Register your business', kind: 'supplier', href: getSafeRoute('supplier.register', '/supplier/register') },
            { label: 'Applicant', description: 'Apply for jobs', kind: 'applicant', href: getSafeRoute('applicant.register', '/applicant/register') },
        ],
    },
]);

const toggleMenu = (key: string) => {
    openMenu.value = openMenu.value === key ? null : key;
};

const closeMenus = () => {
    openMenu.value = null;
    mobileOpen.value = false;
};

const handleDocumentClick = (event: MouseEvent) => {
    const target = event.target as HTMLElement | null;
    if (!target?.closest('.auth-menu')) openMenu.value = null;
    if (!target || !navWrapRef.value?.contains(target)) mobileOpen.value = false;
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') closeMenus();
};
</script>

<template>
    <Head title="Monti ERP | Industrial Textile Management" />

    <div class="lp-root relative flex min-h-screen w-full flex-col overflow-x-clip">

        <!-- Scroll progress thread -->
        <div ref="progressBar" class="scroll-progress" aria-hidden="true"></div>

        <!-- Soft background light / shadow shapes -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="lp-blob lp-blob-a"></div>
            <div class="lp-blob lp-blob-b"></div>
            <div class="lp-blob lp-blob-c"></div>
        </div>

        <!-- ============================== HEADER ============================== -->
        <header ref="navWrapRef" class="nav-wrap sticky top-0 z-40 px-3 pt-3 sm:px-6 lg:px-10">
            <div class="relative mx-auto max-w-7xl">
                <div class="nav-pill anim-down flex items-center justify-between gap-3 py-2 pl-4 pr-2 sm:py-2.5 sm:pl-6 sm:pr-3">

                    <!-- Wordmark -->
                    <button type="button" class="group flex items-center gap-2.5 rounded-full focus:outline-none" aria-label="Monti Textile home" @click="switchPage('home')">
                        <img src="/images/applogo.png" alt="" class="h-7 w-7 object-contain transition-transform duration-300 group-hover:scale-105 sm:h-8 sm:w-8" />
                        <span class="wordmark text-[1.65rem] leading-none">monti<span class="t-accent">.</span></span>
                    </button>

                    <!-- Center links -->
                    <nav aria-label="Main navigation" class="hidden items-center gap-1 md:flex">
                        <button
                            v-for="item in navItems"
                            :key="item.id"
                            type="button"
                            class="nav-link"
                            :class="activePage === item.id ? 'is-active' : ''"
                            :aria-current="activePage === item.id ? 'page' : undefined"
                            @click="switchPage(item.id)"
                        >
                            {{ item.label }}
                        </button>
                    </nav>

                    <!-- Right actions -->
                    <div class="flex items-center gap-2">
                        <div v-for="menu in authMenus" :key="menu.id" class="auth-menu relative hidden md:block">
                            <button
                                type="button"
                                aria-haspopup="menu"
                                :aria-expanded="openMenu === 'header:' + menu.id"
                                class="btn-ghost inline-flex min-h-11 items-center gap-2 px-5 text-sm font-semibold"
                                @click="toggleMenu('header:' + menu.id)"
                            >
                                {{ menu.label }}
                                <svg class="h-3 w-3 transition-transform duration-200" :class="openMenu === 'header:' + menu.id ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="opacity-0 -translate-y-2 scale-95"
                                enter-to-class="opacity-100 translate-y-0 scale-100"
                                leave-active-class="transition duration-150 ease-in"
                                leave-from-class="opacity-100 translate-y-0 scale-100"
                                leave-to-class="opacity-0 -translate-y-2 scale-95"
                            >
                                <div v-if="openMenu === 'header:' + menu.id" role="menu" class="pop-menu absolute right-0 top-full z-30 mt-3 w-72 origin-top-right p-2 text-left">
                                    <div class="px-3 pb-2 pt-2">
                                        <p class="text-sm font-bold t-ink">{{ menu.heading }}</p>
                                        <p class="mt-0.5 text-[12px] leading-snug t-muted">{{ menu.subheading }}</p>
                                    </div>
                                    <Link
                                        v-for="item in menu.items"
                                        :key="item.label"
                                        :href="item.href"
                                        role="menuitem"
                                        class="menu-item group flex items-center gap-3 rounded-2xl px-2.5 py-2.5"
                                        @click="closeMenus"
                                    >
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset transition-all duration-150 group-hover:scale-105" :class="menuTones[item.kind]">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" :d="menuIcons[item.kind]" />
                                            </svg>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[13px] font-semibold t-ink">{{ item.label }}</span>
                                            <span class="block truncate text-[12px] t-muted">{{ item.description }}</span>
                                        </span>
                                        <svg class="h-4 w-4 shrink-0 -translate-x-1 t-accent opacity-0 transition-all duration-150 group-hover:translate-x-0 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </div>
                            </Transition>
                        </div>

                        <Link :href="getSafeRoute('login', '/login')" class="btn-dark group inline-flex min-h-11 items-center gap-2 px-5 text-sm font-semibold sm:px-6">
                            Login
                            <svg class="arrow h-4 w-4 t-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </Link>

                        <button
                            type="button"
                            class="btn-ghost inline-flex h-11 w-11 items-center justify-center md:hidden"
                            :aria-expanded="mobileOpen"
                            aria-label="Toggle menu"
                            @click.stop="mobileOpen = !mobileOpen"
                        >
                            <svg v-if="!mobileOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" />
                            </svg>
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Mobile menu -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="mobileOpen" class="pop-menu absolute inset-x-0 top-full mt-2 p-3 md:hidden" @click.stop>
                        <nav aria-label="Mobile navigation" class="grid gap-1">
                            <button
                                v-for="item in navItems"
                                :key="item.id"
                                type="button"
                                class="nav-link text-left"
                                :class="activePage === item.id ? 'is-active' : ''"
                                @click="switchPage(item.id)"
                            >
                                {{ item.label }}
                            </button>
                        </nav>
                        <div v-for="menu in authMenus" :key="menu.id" class="mt-3 border-t border-[color:var(--line)] pt-3">
                            <p class="px-3 pb-1 text-[11px] font-bold uppercase tracking-[0.2em] t-muted">{{ menu.heading }}</p>
                            <Link
                                v-for="item in menu.items"
                                :key="item.label"
                                :href="item.href"
                                class="menu-item group flex items-center gap-3 rounded-2xl px-3 py-2.5"
                                @click="closeMenus"
                            >
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset" :class="menuTones[item.kind]">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="menuIcons[item.kind]" />
                                    </svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-semibold t-ink">{{ item.label }}</span>
                                    <span class="block truncate text-[12px] t-muted">{{ item.description }}</span>
                                </span>
                            </Link>
                        </div>
                    </div>
                </Transition>
            </div>
        </header>

        <!-- ============================== HOME HERO ============================== -->
        <section v-if="activePage === 'home'" class="hero relative">
            <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-10">
                <div class="relative z-10 flex flex-col pb-6 pt-10 sm:pt-14 lg:min-h-[660px] lg:w-[46%] lg:justify-center lg:pb-20 lg:pt-8">
                    <p class="eyebrow anim-up">Enterprise Resource Planning</p>

                    <h1 class="hero-title anim-up mt-5" style="animation-delay: 80ms">
                        <span class="block">Let's</span>
                        <span class="block">Weave</span>
                        <span class="block t-accent shine-text">The Future.</span>
                    </h1>

                    <p class="anim-up mt-6 max-w-[420px] text-[1.05rem] leading-relaxed t-muted sm:text-lg" style="animation-delay: 160ms">
                        Precision management for Monti Textile's manufacturing lifecycle. From raw weaving to global
                        distribution, keep every thread accounted for.
                    </p>

                    <!-- Search / quote bar -->
                    <form class="search-bar anim-up mt-9 flex w-full max-w-[520px] items-center gap-2 py-2 pl-4 pr-2" style="animation-delay: 240ms" role="search" @submit.prevent="runHeroSearch">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg t-accent" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                            </svg>
                        </span>
                        <label class="sr-only" for="hero-product">Search products</label>
                        <input id="hero-product" v-model="productSearch" type="text" placeholder="Search products" class="bare-input min-w-0 flex-1" />
                        <span class="h-6 w-px shrink-0 bg-[var(--line)]" aria-hidden="true"></span>
                        <label class="sr-only" for="hero-category">Category</label>
                        <select id="hero-category" v-model="productCategory" class="bare-input min-w-0 flex-1">
                            <option value="">All categories</option>
                            <option v-for="category in productCategories" :key="category" :value="category">{{ category }}</option>
                        </select>
                        <button type="submit" class="circle-btn flex h-11 w-11 shrink-0 items-center justify-center" aria-label="Browse products">
                            <svg class="arrow h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </button>
                    </form>

                    <!-- Stats -->
                    <dl class="anim-up mt-10 flex items-stretch" style="animation-delay: 320ms">
                        <div class="stat">
                            <dd v-countup class="stat-num">10K+</dd>
                            <dt class="stat-label">Products</dt>
                        </div>
                        <div class="stat">
                            <dd v-countup class="stat-num">500+</dd>
                            <dt class="stat-label">Partners</dt>
                        </div>
                        <div class="stat">
                            <dd v-countup class="stat-num">98%</dd>
                            <dt class="stat-label">Satisfaction</dt>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Isometric illustration (bleeds off the right and bottom edges) -->
            <div class="hero-art anim-art" aria-hidden="false">
                <svg class="h-full w-full" viewBox="0 0 1000 800" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Isometric illustration of a Monti Textile warehouse loading boxes onto a delivery truck">
                    <defs>
                        <linearGradient id="gGround" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#FFFFFF" />
                            <stop offset="1" stop-color="#F8ECE2" />
                        </linearGradient>
                        <linearGradient id="gLeft" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#F9F4EF" />
                            <stop offset="1" stop-color="#E9DFD5" />
                        </linearGradient>
                        <linearGradient id="gRight" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#FFFFFF" />
                            <stop offset="1" stop-color="#F0E8E0" />
                        </linearGradient>
                        <linearGradient id="gOrange" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#FF8A3D" />
                            <stop offset="1" stop-color="#FF6A00" />
                        </linearGradient>
                        <linearGradient id="gDoor" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#FF7A1A" />
                            <stop offset="1" stop-color="#D94F00" />
                        </linearGradient>
                        <linearGradient id="gGlass" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#4A505C" />
                            <stop offset="1" stop-color="#262A33" />
                        </linearGradient>
                        <radialGradient id="gLeaf" cx=".35" cy=".3" r=".8">
                            <stop offset="0" stop-color="#8DAE78" />
                            <stop offset="1" stop-color="#4C6E3F" />
                        </radialGradient>
                        <filter id="fBlur" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="22" /></filter>
                        <filter id="fBlurS" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="9" /></filter>
                        <filter id="fGlow" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="6" /></filter>

                        <!-- cardboard box -->
                        <g id="lpBox">
                            <polygon points="0,0 -32,-16 -32,-52 0,-36" fill="#D49A63" />
                            <polygon points="0,0 32,-16 32,-52 0,-36" fill="#C0834D" />
                            <polygon points="0,-36 -32,-52 0,-68 32,-52" fill="#E8BE8F" />
                            <path d="M-16 -60L16 -44" stroke="#F6DDBB" stroke-width="5" stroke-linecap="round" />
                        </g>
                        <!-- potted tree -->
                        <g id="lpTree">
                            <polygon points="0,0 -28,-14 -28,-40 0,-26" fill="#F2ECE6" />
                            <polygon points="0,0 28,-14 28,-40 0,-26" fill="#E1D8CE" />
                            <polygon points="0,-26 -28,-40 0,-54 28,-40" fill="#FFFFFF" />
                            <polygon points="0,-30 -20,-40 0,-50 20,-40" fill="#6B5A4A" />
                            <path d="M0 -44V-86" stroke="#7A5C3E" stroke-width="5" stroke-linecap="round" />
                            <circle cx="0" cy="-112" r="34" fill="url(#gLeaf)" />
                            <circle cx="-24" cy="-92" r="24" fill="url(#gLeaf)" />
                            <circle cx="24" cy="-92" r="24" fill="url(#gLeaf)" />
                            <circle cx="0" cy="-136" r="22" fill="url(#gLeaf)" />
                        </g>
                    </defs>

                    <!-- background glow -->
                    <ellipse cx="320" cy="70" rx="280" ry="90" fill="#FFFFFF" opacity=".65" filter="url(#fBlur)" />

                    <!-- ground -->
                    <polygon points="500,170 1300,570 500,970 -300,570" fill="url(#gGround)" stroke="#FFFFFF" stroke-width="6" stroke-linejoin="round" />

                    <!-- soft shadows -->
                    <polygon points="420,470 660,350 830,435 590,555" fill="#B98A62" opacity=".16" filter="url(#fBlurS)" />
                    <ellipse cx="800" cy="622" rx="170" ry="48" fill="#8A6A4F" opacity=".22" filter="url(#fBlurS)" />

                    <!-- orange ribbed panel behind the building -->
                    <polygon points="650,385 760,330 760,50 650,105" fill="url(#gOrange)" stroke="#FF7A1A" stroke-width="6" stroke-linejoin="round" />
                    <path d="M664 378V98M678 371V91M692 364V84M706 357V77M720 350V70M734 343V63M748 336V56" stroke="#FFFFFF" stroke-opacity=".22" stroke-width="2" />
                    <g transform="translate(662 215) skewY(-26.565)" fill="#FFFFFF" fill-opacity=".92" font-size="14" font-weight="600" letter-spacing=".5">
                        <text y="0">Fabric</text>
                        <text y="20">People</text>
                        <text y="40">Possibilities</text>
                    </g>

                    <!-- building -->
                    <polygon points="420,470 200,360 200,150 420,260" fill="url(#gLeft)" stroke="#EEE5DC" stroke-width="10" stroke-linejoin="round" />
                    <polygon points="420,470 660,350 660,140 420,260" fill="url(#gRight)" stroke="#F7F1EB" stroke-width="10" stroke-linejoin="round" />
                    <polygon points="420,260 200,150 440,30 660,140" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="10" stroke-linejoin="round" />
                    <polygon points="423,226 269,149 437,65 591,142" fill="#EDE3D9" stroke="#E4D9CE" stroke-width="4" stroke-linejoin="round" />

                    <text transform="translate(232 262) skewY(26.565)" font-size="42" font-weight="800" fill="#1A1A1A" letter-spacing="-1.5">monti<tspan fill="#FF6A00">.</tspan></text>
                    <text transform="translate(232 302) skewY(26.565)" font-size="16" font-weight="600" fill="#8B837B">Weaving what matters</text>

                    <!-- conveyor -->
                    <polygon points="468,446 576,392 756,482 648,536" fill="#3A3A40" stroke="#3A3A40" stroke-width="6" stroke-linejoin="round" />
                    <path d="M468 446L648 536" stroke="#FF6A00" stroke-width="7" stroke-linecap="round" opacity=".9" filter="url(#fGlow)" />
                    <path class="belt-flow" d="M468 446L648 536" stroke="#FF8A3D" stroke-width="3" stroke-linecap="round" stroke-dasharray="10 12" />

                    <!-- glowing door -->
                    <polygon points="468,446 576,392 576,272 468,326" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="14" stroke-linejoin="round" />
                    <polygon class="door-glow" points="468,446 576,392 576,272 468,326" fill="#FF6A00" opacity=".75" filter="url(#fGlow)" />
                    <polygon points="468,446 576,392 576,272 468,326" fill="url(#gDoor)" />
                    <polygon points="468,326 576,272 576,292 468,346" fill="#000000" opacity=".14" />

                    <!-- boxes on the conveyor -->
                    <g class="belt-box" style="animation-delay: 0s"><use href="#lpBox" x="525" y="440" /></g>
                    <g class="belt-box" style="animation-delay: -1.7s"><use href="#lpBox" transform="translate(590 486) scale(.9)" /></g>
                    <g class="belt-box" style="animation-delay: -3.3s"><use href="#lpBox" transform="translate(640 494) scale(.8)" /></g>

                    <!-- sparks drifting up from the glowing door -->
                    <circle class="spark" cx="500" cy="318" r="3.5" fill="#FFB27A" style="animation-delay: 0s" />
                    <circle class="spark" cx="530" cy="304" r="2.8" fill="#FFC48F" style="animation-delay: -1.2s" />
                    <circle class="spark" cx="556" cy="292" r="3.2" fill="#FFB27A" style="animation-delay: -2.3s" />

                    <!-- trees -->
                    <g class="tree-sway"><use href="#lpTree" transform="translate(150 430)" /></g>
                    <g class="tree-sway" style="animation-delay: -2.4s"><use href="#lpTree" transform="translate(810 362) scale(.85)" /></g>

                    <!-- signpost -->
                    <rect x="246" y="470" width="8" height="172" rx="4" fill="#CFC4BA" />
                    <ellipse cx="250" cy="644" rx="24" ry="12" fill="#E2D8CE" />
                    <g class="sign-float" style="animation-delay: 0s"><g transform="translate(258 484) skewY(26.565)"><rect width="126" height="34" rx="12" fill="#FFFFFF" /><text x="63" y="23" text-anchor="middle" font-size="16" font-weight="700" fill="#3A3A3A">Precise</text></g></g>
                    <g class="sign-float" style="animation-delay: -1.1s"><g transform="translate(258 526) skewY(26.565)"><rect width="126" height="34" rx="12" fill="#FFFFFF" /><text x="63" y="23" text-anchor="middle" font-size="16" font-weight="700" fill="#3A3A3A">Reliable</text></g></g>
                    <g class="sign-float" style="animation-delay: -2.2s"><g transform="translate(258 568) skewY(26.565)"><rect width="126" height="34" rx="12" fill="#FFFFFF" /><text x="63" y="23" text-anchor="middle" font-size="16" font-weight="700" fill="#3A3A3A">Global</text></g></g>

                    <!-- truck: body -->
                    <g class="truck-bob">
                    <polygon points="800,612 680,552 680,432 800,492" fill="url(#gLeft)" stroke="#EEE5DC" stroke-width="8" stroke-linejoin="round" />
                    <polygon points="800,612 908,558 908,438 800,492" fill="url(#gRight)" stroke="#F7F1EB" stroke-width="8" stroke-linejoin="round" />
                    <polygon points="800,492 680,432 788,378 908,438" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="8" stroke-linejoin="round" />
                    <polygon points="680,552 800,612 800,596 680,536" fill="#FF6A00" />
                    <text transform="translate(698 482) skewY(26.565)" font-size="30" font-weight="800" fill="#1A1A1A" letter-spacing="-1">monti<tspan fill="#FF6A00">.</tspan></text>

                    <!-- truck: cab -->
                    <polygon points="860,642 800,612 800,532 860,562" fill="url(#gLeft)" stroke="#EEE5DC" stroke-width="8" stroke-linejoin="round" />
                    <polygon points="860,642 968,588 968,508 860,562" fill="url(#gRight)" stroke="#F7F1EB" stroke-width="8" stroke-linejoin="round" />
                    <polygon points="860,562 800,532 908,478 968,508" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="8" stroke-linejoin="round" />
                    <polygon points="873,594 955,552 955,524 873,566" fill="url(#gGlass)" />
                    <circle cx="880" cy="622" r="5" fill="#FFC48F" /><circle cx="880" cy="622" r="9" fill="#FF8A3D" opacity=".5" filter="url(#fGlow)" />
                    <circle cx="950" cy="587" r="5" fill="#FFC48F" /><circle cx="950" cy="587" r="9" fill="#FF8A3D" opacity=".5" filter="url(#fGlow)" />

                    <!-- wheels -->
                    <g transform="translate(715 570) skewY(26.565)"><circle r="17" fill="#2B2B30" /><circle r="7" fill="#E9E2DB" /></g>
                    <g transform="translate(830 628) skewY(26.565)"><circle r="17" fill="#2B2B30" /><circle r="7" fill="#E9E2DB" /></g>

                    </g>

                    <!-- floor text -->
                    <text transform="matrix(0.866 -0.5 0.866 0.5 380 740)" font-size="32" font-weight="700" fill="#B3A79B" fill-opacity=".6" letter-spacing="1">More Than Fabric</text>

                    <!-- blurred foreground for depth -->
                    <ellipse cx="120" cy="790" rx="280" ry="90" fill="#FFFFFF" opacity=".7" filter="url(#fBlur)" />
                    <circle cx="30" cy="700" r="80" fill="#7FA06B" opacity=".4" filter="url(#fBlur)" />
                    <ellipse cx="960" cy="770" rx="220" ry="80" fill="#FFD9BE" opacity=".6" filter="url(#fBlur)" />
                </svg>
            </div>
        </section>

        <!-- ============================== MAIN CONTENT ============================== -->
        <main class="relative z-10 mx-auto flex w-full max-w-7xl flex-1 flex-col px-3 sm:px-6 lg:px-10">

            <!-- HOME PAGE -->
            <div v-if="activePage === 'home'" class="page-enter flex flex-1 flex-col pb-6 pt-8 sm:pt-12">

                <section id="company-posts" class="mx-auto mb-20 w-full max-w-3xl scroll-mt-28 shrink-0">
                    <div class="mb-10 text-center" v-reveal>
                        <span class="eyebrow is-accent">From Monti Textile</span>
                        <h2 class="section-title mt-2">Company Updates</h2>
                        <p class="mx-auto mt-2 max-w-md text-sm t-muted">News, celebrations, promotions, and events from our team.</p>
                    </div>

                    <!-- Updates hang on a stitched thread, newest first -->
                    <div v-if="companyPosts?.length" class="thread">
                        <article v-for="(post, pi) in companyPosts" :key="post.id" v-post-visible="post.id" class="thread-item" v-reveal:up="Math.min(pi, 3) * 90">
                            <span class="thread-node" :class="pi === 0 ? 'is-latest' : ''" aria-hidden="true"></span>

                            <div class="post-card card overflow-hidden">
                                <div class="post-text p-6">
                                    <div class="mb-4 flex items-center gap-3">
                                        <span class="post-avatar" aria-hidden="true">{{ postInitials(post.creator) }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-bold t-ink">{{ post.creator || 'Monti Textile' }}</p>
                                            <time v-if="post.created_at" :datetime="post.created_at" class="text-xs t-muted">{{ new Date(post.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) }}</time>
                                        </div>
                                        <span v-if="pi === 0" class="post-badge">Latest</span>
                                        <span v-if="post.created_at" class="post-time">{{ getRelativeTime(post.created_at) }}</span>
                                    </div>
                                    <p class="whitespace-pre-wrap text-[15px] leading-relaxed t-ink">{{ post.caption }}</p>
                                </div>

                                <div v-if="post.media?.length" class="px-3 pb-3">
                                    <div class="relative overflow-hidden rounded-3xl">
                                        <video v-if="currentPostMedia(post)?.type === 'video'" :key="currentPostMedia(post).url" :src="currentPostMedia(post).url" controls class="lp-post-media" />
                                        <img v-else :src="currentPostMedia(post)?.url" :alt="`Monti Textile post image ${(postMediaIndexes[post.id] || 0) + 1}`" class="lp-post-media" loading="lazy" decoding="async" />
                                        <template v-if="post.media.length > 1">
                                            <span class="media-count">{{ (postMediaIndexes[post.id] || 0) + 1 }}/{{ post.media.length }}</span>
                                            <button type="button" class="carousel-btn absolute left-3 top-1/2 -translate-y-1/2" :aria-label="`Previous image for post ${post.id}`" @click="changePostMedia(post, -1)">&#8249;</button>
                                            <button type="button" class="carousel-btn absolute right-3 top-1/2 -translate-y-1/2" :aria-label="`Next image for post ${post.id}`" @click="changePostMedia(post, 1)">&#8250;</button>
                                            <div class="absolute bottom-3 left-0 right-0 flex justify-center gap-2">
                                                <button v-for="(_, index) in post.media" :key="index" type="button" class="h-2 rounded-full shadow transition-all" :class="(postMediaIndexes[post.id] || 0) === index ? 'w-5 bg-white' : 'w-2 bg-white/70'" :aria-label="`Show item ${index + 1} of post ${post.id}`" @click="postMediaIndexes[post.id] = index" />
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div v-else class="card p-10 text-center text-sm t-muted" v-reveal:zoom>Company news and updates will appear here.</div>
                </section>

                <section id="products" class="mb-20 w-full scroll-mt-28 shrink-0">
                    <div class="mb-8 text-center" v-reveal>
                        <span class="eyebrow is-accent">Monti Textile Catalog</span>
                        <h2 class="section-title mt-2">Products We Offer</h2>
                        <p class="mx-auto mt-2 max-w-md text-sm t-muted">Slide through the catalog. Orders are available to approved business clients.</p>
                    </div>

                    <div v-if="products?.length" v-reveal:zoom>
                        <!-- Category pills (shared with the hero search) -->
                        <div v-if="productCategories.length" class="cover-pills" role="group" aria-label="Filter products by category">
                            <button type="button" class="cover-pill" :class="!productCategory ? 'is-on' : ''" :aria-pressed="!productCategory" @click="productCategory = ''">All</button>
                            <button v-for="category in productCategories" :key="category" type="button" class="cover-pill" :class="productCategory === category ? 'is-on' : ''" :aria-pressed="productCategory === category" @click="productCategory = category">{{ category }}</button>
                        </div>

                        <div v-if="filteredProducts.length">
                            <div
                                class="cover-stage"
                                tabindex="0"
                                role="region"
                                aria-roledescription="carousel"
                                aria-label="Products we offer"
                                @keydown.left.prevent="prevProduct"
                                @keydown.right.prevent="nextProduct"
                                @pointerdown="onCoverPointerDown"
                                @pointerup="onCoverPointerUp"
                                @pointercancel="onCoverPointerCancel"
                                @mouseenter="productPaused = true"
                                @mouseleave="productPaused = false"
                                @focusin="productPaused = true"
                                @focusout="productPaused = false"
                            >
                                <article
                                    v-for="item in visibleProducts"
                                    :key="item.product.id"
                                    class="cover-card"
                                    :class="coverOffset(item.i) === 0 ? 'is-active' : ''"
                                    :style="coverStyle(item.i)"
                                    :aria-hidden="Math.abs(coverOffset(item.i)) > 2"
                                    :aria-label="item.product.name"
                                    @click="onCoverClick(item.i)"
                                >
                                    <img v-if="currentProductImage(item.product)?.url" :src="currentProductImage(item.product).url" :alt="item.product.name" class="cover-img" draggable="false" decoding="async" />
                                    <div v-else class="cover-empty">{{ item.product.name.charAt(0) }}</div>

                                    <div class="cover-shade" :style="{ opacity: Math.min(Math.abs(coverOffset(item.i)) * 0.32, 0.7) }"></div>

                                    <div v-if="coverOffset(item.i) === 0 && item.product.images?.length > 1" class="cover-dots" aria-hidden="true">
                                        <span v-for="(_, index) in item.product.images" :key="index" :class="(productImageIndexes[item.product.id] || 0) === index ? 'is-on' : ''"></span>
                                    </div>

                                    <div class="cover-info">
                                        <span v-if="item.product.category && item.product.category.toLowerCase() !== 'uncategorized'" class="cover-cat">{{ item.product.category }}</span>
                                        <p class="cover-name">{{ item.product.name }}</p>
                                    </div>
                                </article>
                            </div>

                            <div class="cover-controls">
                                <button type="button" class="cover-nav" aria-label="Previous product" @click="prevProduct">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M11 6l-6 6 6 6" /></svg>
                                </button>
                                <span class="cover-count" aria-live="polite">{{ (productIndex % filteredProducts.length) + 1 }} / {{ filteredProducts.length }}</span>
                                <button type="button" class="cover-nav" aria-label="Next product" @click="nextProduct">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
                                </button>
                            </div>
                        </div>
                        <div v-else class="card p-10 text-center">
                            <p class="text-sm t-muted">No products match your search.</p>
                            <button type="button" class="mt-3 text-sm font-semibold t-accent-ink hover:underline" @click="productSearch = ''; productCategory = ''">Clear search</button>
                        </div>
                    </div>
                    <div v-else class="card p-10 text-center text-sm t-muted">Our product catalog will be available here soon.</div>
                    <p class="mt-6 text-center text-xs t-muted">Purchasing is handled through Monti Textile's official business client accounts.</p>
                </section>

                <section class="mb-16 w-full shrink-0">
                    <div class="card p-6 sm:p-10" v-reveal:zoom>
                        <div class="grid items-center gap-10 md:grid-cols-2">
                            <div>
                                <span class="eyebrow is-accent">About Monti Textile</span>
                                <h2 class="section-title mt-2">Leading the Textile Industry</h2>
                                <p class="mt-3 max-w-md text-[15px] leading-relaxed t-muted">
                                    Monti Textile is a premier manufacturer of high-quality textile products, serving
                                    global markets with innovative solutions.
                                </p>
                                <ul class="mt-5 space-y-3">
                                    <li v-for="(point, pi) in ['ISO 9001:2015 Certified', 'Sustainable Manufacturing Practices', 'Global Distribution Network']" :key="point" v-reveal:left="300 + pi * 130" class="flex items-center text-sm font-medium t-ink">
                                        <span class="mr-3 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[var(--accent-50)] t-accent-ink">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                        {{ point }}
                                    </li>
                                </ul>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div v-for="(stat, si) in [{ n: '100+', l: 'Employees' }, { n: '50+', l: 'Countries' }, { n: '1M+', l: 'Units Produced' }, { n: '98%', l: 'Client Retention' }]" :key="stat.l" v-reveal:zoom="200 + si * 110" class="tile p-5 text-center">
                                    <div v-countup class="text-3xl font-extrabold t-ink">{{ stat.n }}</div>
                                    <div class="mt-1 text-xs font-medium t-muted">{{ stat.l }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mb-16 w-full shrink-0">
                    <div class="mb-8 text-center" v-reveal>
                        <span class="eyebrow is-accent">Why Choose Us</span>
                        <h2 class="section-title mt-2">Excellence in Every Thread</h2>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="(item, ii) in [{ i: '🏆', t: 'Quality First', d: 'Uncompromising quality standards' }, { i: '🔬', t: 'Innovation', d: 'Cutting-edge technology' }, { i: '🌱', t: 'Sustainability', d: 'Eco-friendly practices' }, { i: '🤝', t: 'Partnership', d: 'Long-term relationships' }]" :key="item.t" v-reveal:zoom="ii * 110" class="card card-lift p-6">
                            <div class="icon-float mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-[var(--accent-50)] text-2xl">{{ item.i }}</div>
                            <h4 class="text-base font-bold t-ink">{{ item.t }}</h4>
                            <p class="mt-1 text-sm t-muted">{{ item.d }}</p>
                        </div>
                    </div>
                </section>

                <section id="services" class="mb-16 w-full shrink-0 scroll-mt-28">
                    <div class="mb-10 text-center" v-reveal>
                        <span class="eyebrow is-accent">Our Services</span>
                        <h2 class="section-title mt-2">What We Offer</h2>
                        <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed t-muted sm:text-base">
                            A full range of software and technology services built around the way textile businesses really work.
                        </p>
                        <span class="title-rule" aria-hidden="true"></span>
                    </div>
                    <div class="svc-grid svc-grid-4">
                        <article
                            v-for="(service, si) in services.slice(0, 4)"
                            :key="service.title"
                            v-reveal:up="si * 120"
                            class="svc-card"
                            :class="'tone-' + (si % 4)"
                        >
                            <div class="svc-visual" aria-hidden="true">
                                <span class="svc-orb svc-orb-a"></span>
                                <span class="svc-orb svc-orb-b"></span>
                                <span class="svc-num">0{{ si + 1 }}</span>
                                <span class="svc-icon">
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" :d="service.path" /></svg>
                                </span>
                                <span class="svc-tag">{{ service.tag }}</span>
                            </div>
                            <div class="svc-body">
                                <h3 class="svc-title">{{ service.title }}</h3>
                                <p class="svc-desc">{{ service.description }}</p>
                                <button type="button" class="svc-link" :aria-label="`Learn more about ${service.title}`" @click="learnMore(service)">
                                    Learn More
                                    <svg class="arrow h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </div>
                            <span class="svc-bar" aria-hidden="true"></span>
                        </article>
                    </div>
                    <div class="mt-8 text-center" v-reveal>
                        <button type="button" class="link-arrow" @click="switchPage('services')">
                            View all services
                            <svg class="arrow h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
                        </button>
                    </div>
                </section>

                <section class="mb-10 w-full shrink-0">
                    <div class="mb-8 text-center" v-reveal>
                        <span class="eyebrow is-accent">Careers</span>
                        <h2 class="section-title mt-2">Join Our Team</h2>
                    </div>
                    <div class="card mx-auto max-w-4xl divide-y divide-black/5 p-6 sm:px-8" v-reveal:zoom>
                        <div v-for="(job, ji) in jobs.slice(0, 3)" :key="job.id" v-reveal:left="150 + ji * 120" class="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0 last:pb-0">
                            <div>
                                <h4 class="text-base font-bold t-ink">{{ job.title }}</h4>
                                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs t-muted">
                                    <span>{{ job.department }}</span>
                                    <span aria-hidden="true">•</span>
                                    <span>{{ job.work_location || 'On-site' }}</span>
                                    <span aria-hidden="true">•</span>
                                    <span :class="getEmploymentTypeBadge(job.employment_type)" class="rounded-full border px-2.5 py-0.5 font-medium">
                                        {{ getEmploymentTypeIcon(job.employment_type) }} {{ job.employment_type }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-xs t-muted">{{ getPostedDisplay(job) }}</span>
                                <button type="button" class="link-arrow" @click="applyForJob(job)">
                                    Apply
                                    <svg class="arrow h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
                                </button>
                            </div>
                        </div>
                        <div v-if="jobs.length === 0" class="py-8 text-center">
                            <p class="text-sm t-ink">No active job openings at the moment.</p>
                            <p class="mt-1 text-xs t-muted">Please check back later.</p>
                        </div>
                    </div>
                    <div class="mt-6 text-center">
                        <button type="button" class="link-arrow" @click="switchPage('careers')">
                            View all openings
                            <svg class="arrow h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
                        </button>
                    </div>
                </section>

            </div>

            <!-- ABOUT PAGE -->
            <div v-if="activePage === 'about'" class="page-enter flex flex-1 flex-col py-8 sm:py-12">
                <div class="mx-auto w-full max-w-4xl">
                    <div class="mb-10 text-center">
                        <span class="eyebrow is-accent">About Us</span>
                        <h1 class="page-title mt-3">Our Story</h1>
                        <p class="mx-auto mt-3 max-w-xl text-base t-muted">Driving innovation in textile manufacturing since 2000.</p>
                    </div>

                    <div class="card mb-6 p-6 md:p-10">
                        <h2 class="text-xl font-bold t-ink">Company Story</h2>
                        <p class="mt-3 text-[15px] leading-relaxed t-muted">
                            Founded in 2000, Monti Textile has grown from a small weaving operation to a global leader
                            in textile manufacturing. Our journey is defined by innovation, quality, and a commitment to
                            sustainable practices.
                        </p>
                        <p class="mt-4 text-[15px] leading-relaxed t-muted">
                            Today, we serve clients across 50+ countries, producing over 1 million units annually.
                            Our state-of-the-art facilities and dedicated team ensure we deliver excellence in every product.
                        </p>
                    </div>

                    <div class="mb-6 grid gap-4 md:grid-cols-3">
                        <div v-for="item in [{ i: '🎯', t: 'Mission', d: 'To deliver innovative textile solutions that exceed expectations.' }, { i: '👁️', t: 'Vision', d: 'To be the global leader in sustainable textile manufacturing.' }, { i: '💎', t: 'Core Values', d: 'Innovation, Quality, Integrity, Sustainability.' }]" :key="item.t" class="card p-6 text-center">
                            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[var(--accent-50)] text-2xl">{{ item.i }}</div>
                            <h4 class="text-base font-bold t-ink">{{ item.t }}</h4>
                            <p class="mt-1 text-sm t-muted">{{ item.d }}</p>
                        </div>
                    </div>

                    <div class="mb-6 grid gap-4 sm:grid-cols-2">
                        <div v-for="value in coreValues" :key="value.title" class="card card-lift p-6">
                            <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-2xl bg-[var(--accent-50)] text-xl">{{ value.icon }}</div>
                            <h4 class="text-base font-bold t-ink">{{ value.title }}</h4>
                            <p class="mt-1 text-sm t-muted">{{ value.description }}</p>
                        </div>
                    </div>

                    <div class="card p-6 md:p-8">
                        <h2 class="mb-5 text-xl font-bold t-ink">Leadership Team</h2>
                        <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                            <div v-for="leader in leadership" :key="leader.name" class="tile p-5 text-center">
                                <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--surface)] text-xl font-extrabold t-accent-ink shadow-[0_6px_20px_var(--accent-soft)]">
                                    {{ leader.avatar }}
                                </div>
                                <h4 class="text-sm font-bold t-ink">{{ leader.name }}</h4>
                                <p class="text-xs font-semibold t-accent-ink">{{ leader.role }}</p>
                                <p class="mt-1.5 text-xs t-muted">{{ leader.bio }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 text-center">
                        <button type="button" class="back-link" @click="switchPage('home')">← Back to Home</button>
                    </div>
                </div>
            </div>

            <!-- SERVICES PAGE -->
            <div v-if="activePage === 'services'" class="page-enter flex flex-1 flex-col py-8 sm:py-12">
                <div class="mx-auto w-full max-w-6xl">
                    <div class="mb-12 text-center">
                        <span class="eyebrow is-accent">Our Services</span>
                        <h1 class="page-title mt-3">What We Offer</h1>
                        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed t-muted">Comprehensive technology solutions for your business, from first idea to long-term support.</p>
                        <span class="title-rule" aria-hidden="true"></span>
                    </div>

                    <div class="svc-grid svc-grid-3">
                        <article
                            v-for="(service, si) in services"
                            :key="service.title"
                            v-reveal:up="(si % 3) * 120"
                            class="svc-card"
                            :class="'tone-' + (si % 4)"
                        >
                            <div class="svc-visual" aria-hidden="true">
                                <span class="svc-orb svc-orb-a"></span>
                                <span class="svc-orb svc-orb-b"></span>
                                <span class="svc-num">0{{ si + 1 }}</span>
                                <span class="svc-icon">
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" :d="service.path" /></svg>
                                </span>
                                <span class="svc-tag">{{ service.tag }}</span>
                            </div>
                            <div class="svc-body">
                                <h3 class="svc-title">{{ service.title }}</h3>
                                <p class="svc-desc">{{ service.description }}</p>
                                <ul class="svc-features">
                                    <li v-for="feature in service.features" :key="feature">
                                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        {{ feature }}
                                    </li>
                                </ul>
                                <button type="button" class="svc-link" :aria-label="`Learn more about ${service.title}`" @click="learnMore(service)">
                                    Learn More
                                    <svg class="arrow h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </div>
                            <span class="svc-bar" aria-hidden="true"></span>
                        </article>

                        <!-- Call-to-action tile -->
                        <article v-reveal:up="240" class="svc-cta">
                            <span class="svc-cta-glow" aria-hidden="true"></span>
                            <p class="eyebrow" style="color: var(--accent-100)">Custom work</p>
                            <h3 class="mt-3 text-2xl font-extrabold leading-tight text-white">Need something tailored to your mill?</h3>
                            <p class="mt-3 text-sm leading-relaxed text-white/70">Tell us about your process and we will scope a solution with you.</p>
                            <button type="button" class="svc-cta-btn group" @click="switchPage('contact')">
                                Talk to our team
                                <svg class="arrow h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
                            </button>
                        </article>
                    </div>

                    <div class="mt-10 text-center">
                        <button type="button" class="back-link" @click="switchPage('home')">← Back to Home</button>
                    </div>
                </div>
            </div>

            <!-- CAREERS PAGE -->
            <div v-if="activePage === 'careers'" class="page-enter flex flex-1 flex-col py-8 sm:py-12">
                <div class="mx-auto w-full max-w-4xl">
                    <div class="mb-8 text-center" v-reveal>
                        <span class="eyebrow is-accent">Careers</span>
                        <h1 class="page-title mt-3">Join Our Team</h1>
                        <p class="mx-auto mt-3 max-w-xl text-base t-muted">Build your career with Monti Textile and shape the future of manufacturing.</p>
                    </div>

                    <!-- Search & Filters -->
                    <div class="card mb-6 p-5 sm:p-6">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-[color:var(--line)] pb-5">
                            <p class="text-sm t-muted">Already applied? Track your hiring progress in the applicant portal.</p>
                            <div class="flex flex-wrap gap-2">
                                <Link :href="getSafeRoute('applicant.login', '/applicant/login')" class="btn-dark inline-flex min-h-10 items-center px-5 text-xs font-semibold">
                                    Applicant Login
                                </Link>
                                <Link :href="getSafeRoute('applicant.register', '/applicant/register')" class="btn-ghost inline-flex min-h-10 items-center px-5 text-xs font-semibold">
                                    Create Account
                                </Link>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <div class="min-w-[200px] flex-1">
                                <label class="sr-only" for="job-search">Search jobs</label>
                                <input id="job-search" v-model="searchQuery" type="text" placeholder="Search jobs by title, department, skills, or employment type..." class="field w-full" />
                            </div>
                            <div>
                                <label class="sr-only" for="job-type">Employment type</label>
                                <select id="job-type" v-model="filterType" class="field">
                                    <option value="">All Types</option>
                                    <option v-for="type in employmentTypes" :key="type" :value="type">{{ type }}</option>
                                </select>
                            </div>
                            <div v-if="searchQuery || filterType" class="flex items-center">
                                <button type="button" class="back-link" @click="searchQuery = ''; filterType = ''">Clear filters</button>
                            </div>
                        </div>
                        <div class="mt-3 text-xs t-muted">
                            Found {{ filteredJobs.length }} {{ filteredJobs.length === 1 ? 'job' : 'jobs' }}
                        </div>
                    </div>

                    <!-- Job Cards -->
                    <div class="mb-6 space-y-4">
                        <div
                            v-for="(job, ji) in filteredJobs"
                            :key="job.id"
                            v-reveal:up="Math.min(ji, 6) * 70"
                            class="card card-lift cursor-pointer p-6"
                            role="button"
                            tabindex="0"
                            @click="viewJobDetail(job)"
                            @keydown.enter="viewJobDetail(job)"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-lg font-bold t-ink">{{ job.title }}</h4>
                                        <span v-if="job.is_urgent" class="animate-pulse rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[11px] font-bold text-rose-700">
                                            🔴 URGENT
                                        </span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs t-muted">
                                        <span class="rounded-full border border-[color:var(--line)] px-2.5 py-0.5">{{ job.department }}</span>
                                        <span aria-hidden="true">•</span>
                                        <span>{{ job.work_mode || 'On-site' }} {{ getWorkModeIcon(job.work_mode) }}</span>
                                        <span aria-hidden="true">•</span>
                                        <span :class="getEmploymentTypeBadge(job.employment_type)" class="rounded-full border px-2.5 py-0.5 font-medium">
                                            {{ getEmploymentTypeIcon(job.employment_type) }} {{ job.employment_type }}
                                        </span>
                                    </div>
                                    <p class="line-clamp-2 mt-3 text-sm leading-relaxed t-muted">{{ job.job_description }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="block text-xs t-muted">{{ getPostedDisplay(job) }}</span>
                                    <button type="button" class="btn-dark mt-3 inline-flex min-h-10 items-center px-5 text-xs font-semibold" @click.stop="applyForJob(job)">
                                        Apply Now
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-if="filteredJobs.length === 0" class="py-10 text-center">
                            <p class="text-sm t-muted">No jobs found matching your criteria.</p>
                            <button type="button" class="mt-2 text-sm font-semibold t-accent-ink hover:underline" @click="searchQuery = ''; filterType = ''">Clear filters</button>
                        </div>
                    </div>

                    <div class="mt-8 text-center">
                        <button type="button" class="back-link" @click="switchPage('home')">← Back to Home</button>
                    </div>
                </div>
            </div>

            <!-- CONTACT PAGE -->
            <div v-if="activePage === 'contact'" class="page-enter flex flex-1 flex-col py-8 sm:py-12">
                <div class="mx-auto w-full max-w-4xl">
                    <div class="mb-10 text-center">
                        <span class="eyebrow is-accent">Contact Us</span>
                        <h1 class="page-title mt-3">Get In Touch</h1>
                        <p class="mx-auto mt-3 max-w-xl text-base t-muted">We'd love to hear from you. Reach out to us anytime.</p>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="card h-full p-6 sm:p-8">
                            <h4 class="mb-5 text-lg font-bold t-ink">Contact Information</h4>
                            <div class="space-y-5">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[var(--accent-50)] t-accent-ink">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs t-muted">Location</p>
                                        <p class="text-sm font-semibold t-ink">Makati, Philippines</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[var(--accent-50)] t-accent-ink">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs t-muted">Phone</p>
                                        <p class="text-sm font-semibold t-ink">+63 2 8888 7777</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[var(--accent-50)] t-accent-ink">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs t-muted">Email</p>
                                        <p class="text-sm font-semibold t-ink">info@montitextile.com</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card p-6 sm:p-8">
                            <h4 class="mb-5 text-lg font-bold t-ink">Send a Message</h4>
                            <form class="space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold t-muted" for="contact-name">Full Name</label>
                                    <input id="contact-name" type="text" class="field w-full" placeholder="John Doe" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold t-muted" for="contact-email">Email Address</label>
                                    <input id="contact-email" type="email" class="field w-full" placeholder="john@example.com" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold t-muted" for="contact-message">Message</label>
                                    <textarea id="contact-message" rows="4" class="field field-area w-full resize-none" placeholder="Your message here..."></textarea>
                                </div>
                                <button type="submit" class="btn-dark group inline-flex min-h-12 w-full items-center justify-center gap-2 px-5 text-sm font-semibold">
                                    Send Message
                                    <svg class="arrow h-4 w-4 t-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="mt-8 text-center">
                        <button type="button" class="back-link" @click="switchPage('home')">← Back to Home</button>
                    </div>
                </div>
            </div>

            <!-- SHARED FOOTER (used by every page) -->
            <footer class="card mb-6 mt-6 shrink-0 p-6 sm:p-8" v-reveal>
                <!-- Unified login and registration options -->
                <div class="mb-6 flex flex-col items-center gap-3 border-b border-[color:var(--line)] pb-6">
                    <span class="eyebrow">Portal Access</span>
                    <div class="auth-menu relative inline-flex flex-wrap items-center justify-center gap-2">
                        <Link :href="getSafeRoute('login', '/login')" class="btn-dark group inline-flex min-h-11 items-center gap-2 px-6 text-sm font-semibold">
                            Unified Login
                            <svg class="arrow h-4 w-4 t-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
                        </Link>
                        <div v-for="menu in authMenus" :key="menu.id">
                            <button
                                type="button"
                                aria-haspopup="menu"
                                :aria-expanded="openMenu === 'footer:' + menu.id"
                                class="btn-ghost inline-flex min-h-11 items-center gap-2 px-5 text-sm font-semibold"
                                @click="toggleMenu('footer:' + menu.id)"
                            >
                                <svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="menuGlyphs[menu.glyph]" />
                                </svg>
                                {{ menu.label }}
                                <svg class="h-3 w-3 transition-transform duration-200" :class="openMenu === 'footer:' + menu.id ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="opacity-0 translate-y-2 scale-95"
                                enter-to-class="opacity-100 translate-y-0 scale-100"
                                leave-active-class="transition duration-150 ease-in"
                                leave-from-class="opacity-100 translate-y-0 scale-100"
                                leave-to-class="opacity-0 translate-y-2 scale-95"
                            >
                                <div v-if="openMenu === 'footer:' + menu.id" role="menu" class="pop-menu absolute bottom-full left-1/2 z-30 mb-3 w-72 max-w-[calc(100vw-1.5rem)] origin-bottom -translate-x-1/2 p-2 text-left">
                                    <div class="px-3 pb-2 pt-2">
                                        <p class="text-sm font-bold t-ink">{{ menu.heading }}</p>
                                        <p class="mt-0.5 text-[12px] leading-snug t-muted">{{ menu.subheading }}</p>
                                    </div>
                                    <Link
                                        v-for="item in menu.items"
                                        :key="item.label"
                                        :href="item.href"
                                        role="menuitem"
                                        class="menu-item group flex items-center gap-3 rounded-2xl px-2.5 py-2.5"
                                        @click="closeMenus"
                                    >
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset transition-all duration-150 group-hover:scale-105" :class="menuTones[item.kind]">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" :d="menuIcons[item.kind]" />
                                            </svg>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[13px] font-semibold t-ink">{{ item.label }}</span>
                                            <span class="block truncate text-[12px] t-muted">{{ item.description }}</span>
                                        </span>
                                        <svg class="h-4 w-4 shrink-0 -translate-x-1 t-accent opacity-0 transition-all duration-150 group-hover:translate-x-0 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>

                <div class="mb-6 grid gap-8 sm:grid-cols-2 md:grid-cols-4">
                    <div>
                        <p class="wordmark mb-3 text-2xl leading-none">monti<span class="t-accent">.</span></p>
                        <p class="text-sm leading-relaxed t-muted">Precision management for the textile manufacturing lifecycle.</p>
                    </div>
                    <div>
                        <h5 class="mb-3 text-sm font-bold t-ink">Quick Links</h5>
                        <ul class="space-y-2 text-sm">
                            <li><button type="button" class="foot-link" @click="switchPage('about')">About Us</button></li>
                            <li><button type="button" class="foot-link" @click="switchPage('services')">Services</button></li>
                            <li><button type="button" class="foot-link" @click="switchPage('careers')">Careers</button></li>
                            <li><button type="button" class="foot-link" @click="switchPage('contact')">Contact</button></li>
                        </ul>
                    </div>
                    <div>
                        <h5 class="mb-3 text-sm font-bold t-ink">Services</h5>
                        <ul class="space-y-2 text-sm t-muted">
                            <li>Software Development</li>
                            <li>Web Development</li>
                            <li>Cloud Solutions</li>
                        </ul>
                    </div>
                    <div>
                        <h5 class="mb-3 text-sm font-bold t-ink">Contact</h5>
                        <ul class="space-y-2 text-sm t-muted">
                            <li>📍 Makati, Philippines</li>
                            <li>📞 +63 2 8888 7777</li>
                            <li>📧 info@montitextile.com</li>
                        </ul>
                    </div>
                </div>
                <div class="border-t border-[color:var(--line)] pt-5 text-center text-xs t-muted">
                    © 2026 Monti Textile Manufacturing. All rights reserved.
                </div>
            </footer>
        </main>

        <!-- ============================== JOB DETAIL MODAL ============================== -->
        <div
            v-if="showJobDetail && selectedJob"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
            @click.self="closeJobDetail"
        >
            <div class="modal-card max-h-[90vh] w-full max-w-3xl overflow-y-auto" role="dialog" aria-modal="true" :aria-label="selectedJob.title">

                <!-- Modal Header -->
                <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-[color:var(--line)] bg-[var(--surface)] p-6 backdrop-blur-md">
                    <div>
                        <h2 class="text-xl font-extrabold t-ink">{{ selectedJob.title }}</h2>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            <span class="text-xs t-muted">{{ selectedJob.department }}</span>
                            <span class="text-xs t-muted" aria-hidden="true">•</span>
                            <span class="text-xs t-muted">{{ selectedJob.work_location || 'On-site' }}</span>
                            <span class="text-xs t-muted" aria-hidden="true">•</span>
                            <span :class="getEmploymentTypeBadge(selectedJob.employment_type)" class="rounded-full border px-2.5 py-0.5 text-[11px] font-medium">
                                {{ getEmploymentTypeIcon(selectedJob.employment_type) }} {{ selectedJob.employment_type }}
                            </span>
                            <span v-if="selectedJob.is_urgent" class="animate-pulse rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[11px] font-bold text-rose-700">
                                🔴 URGENT
                            </span>
                        </div>
                    </div>
                    <button type="button" class="btn-ghost flex h-10 w-10 shrink-0 items-center justify-center" aria-label="Close" @click="closeJobDetail">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="space-y-4 p-6">

                    <!-- Posted Date & Deadline -->
                    <div class="flex flex-wrap items-center justify-between text-xs t-muted">
                        <span>Posted: <span class="font-semibold t-ink">{{ getPostedDisplay(selectedJob) }}</span></span>
                        <span v-if="selectedJob.closing_date">Deadline: <span class="font-semibold t-accent-ink">{{ selectedJob.closing_date }}</span></span>
                    </div>

                    <!-- Employment Type -->
                    <div class="tile flex items-center justify-between p-4">
                        <div>
                            <h4 class="modal-label">Employment Type</h4>
                            <p class="text-lg font-semibold t-ink">{{ selectedJob.employment_type }}</p>
                        </div>
                        <span :class="getEmploymentTypeBadge(selectedJob.employment_type)" class="rounded-full border px-4 py-2 text-sm font-medium">
                            {{ getEmploymentTypeIcon(selectedJob.employment_type) }} {{ selectedJob.employment_type }}
                        </span>
                    </div>

                    <!-- Position Name -->
                    <div class="tile p-4">
                        <h4 class="modal-label mb-1">Position</h4>
                        <p class="font-semibold t-ink">{{ selectedJob.position_name }}</p>
                    </div>

                    <!-- Job Description -->
                    <div class="tile p-4">
                        <h4 class="modal-label mb-2">Job Description</h4>
                        <p class="text-sm leading-relaxed t-ink">{{ selectedJob.job_description }}</p>
                    </div>

                    <!-- Responsibilities -->
                    <div v-if="selectedJob.responsibilities" class="tile p-4">
                        <h4 class="modal-label mb-2">Key Responsibilities</h4>
                        <div class="whitespace-pre-wrap text-sm leading-relaxed t-ink">{{ selectedJob.responsibilities }}</div>
                    </div>

                    <!-- Qualifications -->
                    <div v-if="selectedJob.qualifications" class="tile p-4">
                        <h4 class="modal-label mb-2">Qualifications</h4>
                        <div class="whitespace-pre-wrap text-sm leading-relaxed t-ink">{{ selectedJob.qualifications }}</div>
                    </div>

                    <!-- Required Skills -->
                    <div v-if="selectedJob.required_skills" class="tile p-4">
                        <h4 class="modal-label mb-2">Required Skills</h4>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="skill in selectedJob.required_skills.split(',').map((s: string) => s.trim())"
                                :key="skill"
                                class="rounded-full bg-[var(--surface)] px-3 py-1 text-xs font-semibold t-accent-ink shadow-[0_4px_14px_rgba(0,0,0,0.06)]"
                            >
                                {{ skill }}
                            </span>
                        </div>
                    </div>

                    <!-- Work Details -->
                    <div class="grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 sm:gap-4">
                        <div class="tile p-4">
                            <h4 class="modal-label mb-1">Work Mode</h4>
                            <p class="t-ink">{{ selectedJob.work_mode || 'On-site' }}</p>
                        </div>
                        <div class="tile p-4">
                            <h4 class="modal-label mb-1">Work Schedule</h4>
                            <p class="t-ink">{{ selectedJob.work_schedule || 'Standard' }}</p>
                        </div>
                    </div>

                    <!-- Salary -->
                    <div class="tile p-4">
                        <h4 class="modal-label mb-1">Salary</h4>
                        <p class="text-lg font-extrabold text-emerald-700">
                            {{ formatSalary(selectedJob.salary_min, selectedJob.salary_max, selectedJob.salary_visibility) }}
                        </p>
                    </div>

                    <!-- Apply Button -->
                    <button type="button" class="btn-dark group inline-flex min-h-12 w-full items-center justify-center gap-2 text-sm font-semibold" @click="applyForJob(selectedJob)">
                        Apply Now
                        <svg class="arrow h-4 w-4 t-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

:global(html) {
    scroll-behavior: smooth;
}

/* ---------- Design tokens ---------- */
.lp-root {
    /* Light (default) — warm cream + orange brand */
    --bg-1: #FFF8F3;
    --bg-2: #FDE9DC;
    --accent: #FF6A00;
    --accent-ink: #C2410C; /* darker orange for small text (AA contrast) */
    --accent-50: #FFF1E6;
    --accent-50-hover: #FFE6D2;
    --accent-100: #FFB27A;
    --accent-light: #FF8A3D;
    --accent-dark: #E25500;
    --accent-soft: rgba(255, 106, 0, 0.12);
    --accent-glow: rgba(255, 106, 0, 0.35);
    --accent-line: rgba(255, 106, 0, 0.45);
    --accent-faint: rgba(255, 106, 0, 0.3);
    --accent-shadow: rgba(255, 106, 0, 0.4);
    --ink: #1A1A1A;
    --muted: #6B6B6B;
    --muted-soft: #75706B;
    --placeholder: #8A847E;
    --surface: #FFFFFF;
    --surface-soft: rgba(255, 255, 255, 0.85);
    --tile: #FBF6F1;
    --field-bg: #F7F2ED;
    --field-focus-bg: #FFFFFF;
    --media-bg: #F4EEE8;
    --nav-hover: #F6F1EC;
    --ghost-bg: #F1EEEA;
    --ghost-bg-hover: #EAE5DF;
    --menu-hover: #FBF6F1;
    --line: rgba(26, 26, 26, 0.12);
    --line-strong: rgba(26, 26, 26, 0.32);
    --btn-bg: #1A1A1A;
    --btn-text: #FFFFFF;
    --pill-on-bg: #1A1A1A;
    --pill-on-text: #FFFFFF;
    --on-accent: #FFFFFF;
    --blob-a: rgba(255, 255, 255, 0.9);
    --blob-b: rgba(255, 186, 140, 0.35);
    --blob-c: rgba(255, 214, 188, 0.45);
    --shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    --shadow-lift: 0 18px 50px rgba(0, 0, 0, 0.12);

    font-family: 'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    color: var(--ink);
    background: linear-gradient(165deg, var(--bg-1) 0%, var(--bg-2) 100%);
    background-attachment: scroll;
    transition: color 0.3s ease;
}

/* =====================================================================
   SYSTEM THEMES
   Each theme re-tokenises .lp-root; every colour below derives from these.
   ===================================================================== */

/* ---------- Dark (warm charcoal) ---------- */
html[data-theme='dark'] .lp-root {
    --bg-1: #17130F;
    --bg-2: #0B0907;
    --accent: #FF8A3D;
    --accent-ink: #FFB27A;
    --accent-50: rgba(255, 138, 61, 0.16);
    --accent-50-hover: rgba(255, 138, 61, 0.24);
    --accent-100: #FFC48F;
    --accent-light: #FFA35C;
    --accent-dark: #C2410C;
    --accent-soft: rgba(255, 138, 61, 0.18);
    --accent-glow: rgba(255, 138, 61, 0.45);
    --accent-line: rgba(255, 138, 61, 0.5);
    --accent-faint: rgba(255, 138, 61, 0.32);
    --accent-shadow: rgba(255, 138, 61, 0.35);
    --ink: #F5EFE6;
    --muted: #B9AEA0;
    --muted-soft: #B9AEA0;
    --placeholder: #8F8478;
    --surface: #221D18;
    --surface-soft: rgba(34, 29, 24, 0.85);
    --tile: #2A241D;
    --field-bg: #2A241D;
    --field-focus-bg: #322A22;
    --media-bg: #1E1915;
    --nav-hover: rgba(255, 255, 255, 0.08);
    --ghost-bg: rgba(255, 255, 255, 0.08);
    --ghost-bg-hover: rgba(255, 255, 255, 0.14);
    --menu-hover: #2A241D;
    --line: rgba(255, 255, 255, 0.12);
    --line-strong: rgba(255, 255, 255, 0.32);
    --btn-bg: #FF8A3D;
    --btn-text: #1A120B;
    --pill-on-bg: #FF8A3D;
    --pill-on-text: #1A120B;
    --on-accent: #1A120B;
    --blob-a: rgba(255, 138, 61, 0.1);
    --blob-b: rgba(255, 138, 61, 0.12);
    --blob-c: rgba(255, 178, 122, 0.08);
    --shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    --shadow-lift: 0 18px 50px rgba(0, 0, 0, 0.6);
}

/* ---------- Blue (ice-blue & navy) ---------- */
html[data-theme='blue'] .lp-root {
    --bg-1: #EBF3FA;
    --bg-2: #D6E8F7;
    --accent: #1A73E8;
    --accent-ink: #155FC0;
    --accent-50: rgba(79, 163, 247, 0.16);
    --accent-50-hover: rgba(79, 163, 247, 0.26);
    --accent-100: #A9D2FA;
    --accent-light: #4FA3F7;
    --accent-dark: #0B1B4F;
    --accent-soft: rgba(26, 115, 232, 0.12);
    --accent-glow: rgba(26, 115, 232, 0.35);
    --accent-line: rgba(26, 115, 232, 0.45);
    --accent-faint: rgba(26, 115, 232, 0.3);
    --accent-shadow: rgba(26, 115, 232, 0.35);
    --ink: #0B1B4F;
    --muted: #4A5B8C;
    --muted-soft: #5A6A99;
    --placeholder: #7D8DB5;
    --surface: #FFFFFF;
    --surface-soft: rgba(255, 255, 255, 0.85);
    --tile: #F5F9FF;
    --field-bg: #EAF2FC;
    --field-focus-bg: #FFFFFF;
    --media-bg: #E7EFFA;
    --nav-hover: #EAF2FC;
    --ghost-bg: #E4EDF9;
    --ghost-bg-hover: #D6E4F5;
    --menu-hover: #F0F6FE;
    --line: rgba(11, 27, 79, 0.12);
    --line-strong: rgba(11, 27, 79, 0.32);
    --btn-bg: #0B1B4F;
    --btn-text: #FFFFFF;
    --pill-on-bg: #0B1B4F;
    --pill-on-text: #FFFFFF;
    --on-accent: #FFFFFF;
    --blob-a: rgba(255, 255, 255, 0.9);
    --blob-b: rgba(79, 163, 247, 0.28);
    --blob-c: rgba(168, 205, 250, 0.4);
    --shadow: 0 10px 40px rgba(11, 27, 79, 0.1);
    --shadow-lift: 0 18px 50px rgba(11, 27, 79, 0.16);
}

/* ---------- Red (rose & wine) ---------- */
html[data-theme='red'] .lp-root {
    --bg-1: #FFF1F2;
    --bg-2: #FFE4E6;
    --accent: #E11D48;
    --accent-ink: #BE123C;
    --accent-50: rgba(251, 113, 133, 0.16);
    --accent-50-hover: rgba(251, 113, 133, 0.26);
    --accent-100: #FDA4AF;
    --accent-light: #FB7185;
    --accent-dark: #7F1D1D;
    --accent-soft: rgba(225, 29, 72, 0.12);
    --accent-glow: rgba(225, 29, 72, 0.35);
    --accent-line: rgba(225, 29, 72, 0.45);
    --accent-faint: rgba(225, 29, 72, 0.3);
    --accent-shadow: rgba(225, 29, 72, 0.35);
    --ink: #4C0519;
    --muted: #9B6B76;
    --muted-soft: #A67782;
    --placeholder: #C49AA3;
    --surface: #FFFFFF;
    --surface-soft: rgba(255, 255, 255, 0.85);
    --tile: #FFF7F8;
    --field-bg: #FDECEF;
    --field-focus-bg: #FFFFFF;
    --media-bg: #FBEAED;
    --nav-hover: #FDECEF;
    --ghost-bg: #FBE3E8;
    --ghost-bg-hover: #F7D3DB;
    --menu-hover: #FFF4F6;
    --line: rgba(76, 5, 25, 0.12);
    --line-strong: rgba(76, 5, 25, 0.32);
    --btn-bg: #4C0519;
    --btn-text: #FFFFFF;
    --pill-on-bg: #4C0519;
    --pill-on-text: #FFFFFF;
    --on-accent: #FFFFFF;
    --blob-a: rgba(255, 255, 255, 0.9);
    --blob-b: rgba(251, 113, 133, 0.28);
    --blob-c: rgba(255, 164, 175, 0.38);
    --shadow: 0 10px 40px rgba(76, 5, 25, 0.1);
    --shadow-lift: 0 18px 50px rgba(76, 5, 25, 0.16);
}

/* ---------- Green (soft sage & eucalyptus) ---------- */
html[data-theme='green'] .lp-root {
    --bg-1: #F1F8F3;
    --bg-2: #DFEDE2;
    --accent: #40916C;
    --accent-ink: #1B4332;
    --accent-50: rgba(183, 228, 199, 0.3);
    --accent-50-hover: rgba(183, 228, 199, 0.5);
    --accent-100: #B7E4C7;
    --accent-light: #52B788;
    --accent-dark: #2D6A4F;
    --accent-soft: rgba(64, 145, 108, 0.12);
    --accent-glow: rgba(64, 145, 108, 0.35);
    --accent-line: rgba(64, 145, 108, 0.45);
    --accent-faint: rgba(64, 145, 108, 0.3);
    --accent-shadow: rgba(64, 145, 108, 0.35);
    --ink: #1B4332;
    --muted: #5C7A66;
    --muted-soft: #6B8A77;
    --placeholder: #8AA895;
    --surface: #FDFFFE;
    --surface-soft: rgba(253, 255, 254, 0.85);
    --tile: #F7FBF8;
    --field-bg: #EDF5EF;
    --field-focus-bg: #FDFFFE;
    --media-bg: #EAF3ED;
    --nav-hover: #EDF5EF;
    --ghost-bg: #E4F0E8;
    --ghost-bg-hover: #D5E8DB;
    --menu-hover: #F3FAF5;
    --line: rgba(27, 67, 50, 0.12);
    --line-strong: rgba(27, 67, 50, 0.32);
    --btn-bg: #1B4332;
    --btn-text: #FFFFFF;
    --pill-on-bg: #1B4332;
    --pill-on-text: #FFFFFF;
    --on-accent: #FFFFFF;
    --blob-a: rgba(255, 255, 255, 0.9);
    --blob-b: rgba(82, 183, 136, 0.28);
    --blob-c: rgba(183, 228, 199, 0.4);
    --shadow: 0 10px 40px rgba(27, 67, 50, 0.1);
    --shadow-lift: 0 18px 50px rgba(27, 67, 50, 0.16);
}

/* Keep the custom pill inputs above the ERP-wide input resets. */
html[data-theme] .lp-root .field {
    background: var(--field-bg) !important;
    border-radius: 9999px !important;
}
html[data-theme] .lp-root .field-area { border-radius: 24px !important; }
html[data-theme] .lp-root .field:focus {
    background: var(--field-focus-bg) !important;
    border-color: var(--accent) !important;
    box-shadow: 0 0 0 4px var(--accent-soft) !important;
}
html[data-theme] .lp-root .field::placeholder { color: var(--placeholder) !important; }
html[data-theme] .lp-root .bare-input {
    background: transparent !important;
    border: 0 !important;
    border-radius: 9999px !important;
}
html[data-theme] .lp-root .bare-input::placeholder { color: var(--placeholder) !important; }

.lp-root :focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
}

/* ---------- Background light / shadow shapes ---------- */
.lp-blob {
    position: absolute;
    border-radius: 9999px;
    filter: blur(80px);
}
.lp-blob-a { top: -8rem; left: -6rem; width: 28rem; height: 28rem; background: var(--blob-a); }
.lp-blob-b { top: 18rem; right: -8rem; width: 30rem; height: 30rem; background: var(--blob-b); }
.lp-blob-c { bottom: 10%; left: -10rem; width: 26rem; height: 26rem; background: var(--blob-c); }

/* ---------- Color helpers ---------- */
.t-ink { color: var(--ink); }
.t-muted { color: var(--muted); }
.t-accent { color: var(--accent); }
.t-accent-ink { color: var(--accent-ink); }

/* ---------- Typography ---------- */
.wordmark { font-weight: 800; letter-spacing: -0.04em; color: var(--ink); }

.eyebrow {
    display: block;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--muted-soft);
}
.eyebrow.is-accent { color: var(--accent-ink); }

.hero-title {
    font-size: clamp(3.25rem, 6.2vw, 6rem);
    line-height: 1;
    font-weight: 800;
    letter-spacing: -0.035em;
    color: var(--ink);
}

.section-title {
    font-size: clamp(1.75rem, 3vw, 2.4rem);
    line-height: 1.1;
    font-weight: 800;
    letter-spacing: -0.025em;
    color: var(--ink);
}

.page-title {
    font-size: clamp(2.5rem, 5vw, 3.75rem);
    line-height: 1;
    font-weight: 800;
    letter-spacing: -0.03em;
    color: var(--ink);
}

/* ---------- Floating pill navbar ---------- */
.nav-pill {
    background: var(--surface);
    border-radius: 9999px;
    box-shadow: var(--shadow);
}

.nav-link {
    padding: 0.55rem 1rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--ink);
    transition: background-color 0.2s ease, color 0.2s ease;
}
.nav-link:hover { background: var(--nav-hover); }
.nav-link.is-active { background: var(--accent-50); color: var(--accent-ink); }

/* ---------- Buttons ---------- */
.btn-dark {
    background: var(--btn-bg);
    color: var(--btn-text);
    border-radius: 9999px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.btn-dark:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.2);
}
.btn-dark:active { transform: translateY(0); }

.btn-ghost {
    background: var(--ghost-bg);
    color: var(--ink);
    border-radius: 9999px;
    transition: background-color 0.2s ease, transform 0.2s ease;
}
.btn-ghost:hover { background: var(--ghost-bg-hover); transform: translateY(-1px); }

.arrow { transition: transform 0.2s ease; }
.btn-dark:hover .arrow,
.circle-btn:hover .arrow,
.link-arrow:hover .arrow { transform: translateX(3px); }

.circle-btn {
    background: var(--accent);
    color: var(--on-accent);
    border-radius: 9999px;
    box-shadow: 0 8px 20px var(--accent-glow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.circle-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 28px var(--accent-glow);
}

.link-arrow {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--accent-ink);
}
.link-arrow:hover { text-decoration: underline; text-underline-offset: 4px; }

.back-link {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--muted);
    transition: color 0.2s ease;
}
.back-link:hover { color: var(--ink); }

.foot-link { color: var(--muted); transition: color 0.2s ease; }
.foot-link:hover { color: var(--accent-ink); }

/* ---------- Surfaces ---------- */
.card {
    background: var(--surface);
    border-radius: 28px;
    box-shadow: var(--shadow);
}
.card-lift { transition: transform 0.25s ease, box-shadow 0.25s ease; }
.card-lift:hover { transform: translateY(-4px); box-shadow: var(--shadow-lift); }

.tile {
    background: var(--tile);
    border-radius: 20px;
}

.pop-menu {
    background: var(--surface);
    border-radius: 28px;
    box-shadow: 0 24px 60px -12px rgba(0, 0, 0, 0.22);
}
.menu-item { transition: background-color 0.15s ease; }
.menu-item:hover { background: var(--menu-hover); }

.modal-card {
    background: var(--surface);
    border-radius: 32px;
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.25);
}
.modal-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--muted-soft);
}

/* ---------- Inputs ---------- */
.field {
    background: var(--field-bg);
    border: 2px solid transparent;
    border-radius: 9999px;
    padding: 0.7rem 1.25rem;
    font-size: 0.875rem;
    color: var(--ink);
    transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
}
.field::placeholder { color: var(--placeholder); }
.field:focus {
    outline: none;
    background: var(--field-focus-bg);
    border-color: var(--accent);
    box-shadow: 0 0 0 4px var(--accent-soft);
}
.field-area { border-radius: 24px; }

.search-bar {
    background: var(--surface);
    border-radius: 9999px;
    box-shadow: var(--shadow);
}
.bare-input {
    background: transparent;
    border: 0;
    padding: 0.6rem 0.5rem;
    font-size: 0.875rem;
    color: var(--ink);
}
.bare-input::placeholder { color: var(--placeholder); }
.bare-input:focus { outline: none; }
.search-bar:focus-within { box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08), 0 0 0 3px var(--accent-glow); }

/* ---------- Stats ---------- */
.stat { padding: 0 1.25rem; }
.stat:first-child { padding-left: 0; }
.stat + .stat { border-left: 1px solid var(--line); }
.stat-num { font-size: 1.75rem; line-height: 1.1; font-weight: 800; letter-spacing: -0.02em; color: var(--ink); }
.stat-label { margin-top: 0.2rem; font-size: 0.8rem; color: var(--muted); }

/* ---------- Carousel controls ---------- */
.carousel-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 9999px;
    background: var(--surface-soft);
    color: var(--ink);
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
    transition: transform 0.2s ease;
}
.carousel-btn:hover { transform: translateY(-50%) scale(1.08); }
.carousel-btn-sm { width: 1.9rem; height: 1.9rem; font-size: 1.05rem; }

.lp-post-media {
    display: block;
    width: 100%;
    min-height: 180px;
    max-height: 70vh;
    object-fit: contain;
    background: var(--media-bg);
    border-radius: 24px;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* ---------- Hero illustration ---------- */
.hero-art {
    position: relative;
    height: 340px;
    margin-top: 0.5rem;
    -webkit-mask-image: linear-gradient(to right, transparent 0, #000 10%), linear-gradient(to bottom, #000 80%, transparent 100%);
    -webkit-mask-composite: source-in;
    mask-image: linear-gradient(to right, transparent 0, #000 10%), linear-gradient(to bottom, #000 80%, transparent 100%);
    mask-composite: intersect;
}

@media (min-width: 640px) {
    .hero-art { height: 440px; }
}

@media (min-width: 1024px) {
    .hero-art {
        position: absolute;
        top: -5.5rem;
        right: 0;
        bottom: -1.5rem;
        width: 57%;
        height: auto;
        margin-top: 0;
    }
}

/* ---------- Entrance animation (one orchestrated moment) ---------- */
@keyframes lp-fade-up {
    from { opacity: 0; transform: translateY(22px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes lp-slide-in {
    from { opacity: 0; transform: translateX(60px); }
    to { opacity: 1; transform: translateX(0); }
}
.anim-up { animation: lp-fade-up 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) both; }
.anim-art { animation: lp-slide-in 0.95s cubic-bezier(0.2, 0.7, 0.2, 1) 0.1s both; }

@media (prefers-reduced-motion: reduce) {
    .anim-up, .anim-art { animation: none; }
    .btn-dark, .btn-ghost, .circle-btn, .card-lift, .arrow { transition: none; }
    :global(html) { scroll-behavior: auto; }
}

/* =====================================================================
   MOTION
   ===================================================================== */

/* ---------- Scroll progress thread ---------- */
.scroll-progress {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    z-index: 60;
    transform-origin: 0 50%;
    background: linear-gradient(90deg, var(--accent-light), var(--accent));
    box-shadow: 0 0 10px var(--accent-glow);
    pointer-events: none;
}

/* ---------- Navbar drop-in + page transitions ---------- */
@keyframes lp-drop {
    from { opacity: 0; transform: translateY(-26px); }
    to { opacity: 1; transform: translateY(0); }
}
.anim-down { animation: lp-drop 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) both; }

@keyframes lp-page-in {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}
.page-enter { animation: lp-page-in 0.55s cubic-bezier(0.2, 0.7, 0.2, 1); }

/* ---------- Scroll reveal (classes toggled by v-reveal) ---------- */
.reveal {
    opacity: 0;
    transition:
        opacity 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--rd, 0ms),
        transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--rd, 0ms);
    will-change: transform, opacity;
}
.reveal-up { transform: translateY(36px); }
.reveal-left { transform: translateX(-52px); }
.reveal-right { transform: translateX(52px); }
.reveal-zoom { transform: scale(0.9) translateY(20px); }
.reveal.is-in { opacity: 1; transform: none; }

/* ---------- Drifting background light ---------- */
@keyframes lp-drift {
    0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
    50% { transform: translate3d(44px, -34px, 0) scale(1.08); }
}
.lp-blob-a { animation: lp-drift 16s ease-in-out infinite; }
.lp-blob-b { animation: lp-drift 21s ease-in-out -6s infinite reverse; }
.lp-blob-c { animation: lp-drift 25s ease-in-out -11s infinite; }

/* ---------- Hero headline shine ---------- */
.shine-text {
    background: linear-gradient(100deg, var(--accent) 0%, var(--accent) 38%, var(--accent-100) 50%, var(--accent) 62%, var(--accent) 100%);
    background-size: 260% 100%;
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    -webkit-text-fill-color: transparent;
    animation: lp-shine 4.8s ease-in-out infinite;
}
@keyframes lp-shine {
    0% { background-position: 100% 0; }
    100% { background-position: -100% 0; }
}

/* ---------- Button shimmer ---------- */
.btn-dark { position: relative; overflow: hidden; }
.btn-dark::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 30%, rgba(255, 255, 255, 0.22) 50%, transparent 70%);
    transform: translateX(-120%);
    pointer-events: none;
}
.btn-dark:hover::after { transform: translateX(120%); transition: transform 0.7s ease; }

/* ---------- Idle icon float ---------- */
@keyframes lp-bob {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}
.icon-float { animation: lp-bob 4s ease-in-out infinite; }
.grid > :nth-child(even) > .icon-float { animation-delay: -1.6s; }
.grid > :nth-child(3n) > .icon-float { animation-delay: -2.8s; }

/* ---------- Hero illustration: a living warehouse ---------- */
@keyframes lp-belt {
    0% { opacity: 0; transform: translate(-34px, -17px); }
    14% { opacity: 1; }
    84% { opacity: 1; }
    100% { opacity: 0; transform: translate(54px, 27px); }
}
.belt-box { animation: lp-belt 5s linear infinite; }

@keyframes lp-dash { to { stroke-dashoffset: -44; } }
.belt-flow { animation: lp-dash 1.4s linear infinite; }

@keyframes lp-pulse {
    0%, 100% { opacity: 0.45; }
    50% { opacity: 0.95; }
}
.door-glow { animation: lp-pulse 2.6s ease-in-out infinite; }

@keyframes lp-spark {
    0% { opacity: 0; transform: translate(0, 0); }
    20% { opacity: 0.95; }
    100% { opacity: 0; transform: translate(16px, -74px); }
}
.spark { animation: lp-spark 3.4s ease-out infinite; }

@keyframes lp-sway {
    0%, 100% { transform: rotate(-1.8deg); }
    50% { transform: rotate(1.8deg); }
}
.tree-sway { transform-box: fill-box; transform-origin: 50% 100%; animation: lp-sway 5s ease-in-out infinite; }

.truck-bob { animation: lp-bob 3.2s ease-in-out infinite; }
.sign-float { animation: lp-bob 3.8s ease-in-out infinite; }

/* ---------- Image cross-fade when a carousel advances ---------- */
@keyframes lp-fade {
    from { opacity: 0; transform: scale(1.04); }
    to { opacity: 1; transform: scale(1); }
}
.lp-fade { animation: lp-fade 0.7s ease both; }

/* =====================================================================
   PRODUCTS COVERFLOW
   ===================================================================== */
.cover-pills {
    display: flex;
    gap: 0.6rem;
    margin-bottom: 0.75rem;
    padding: 0.25rem 0.25rem 0.75rem;
    overflow-x: auto;
    scrollbar-width: none;
}
.cover-pills::-webkit-scrollbar { display: none; }
@media (min-width: 768px) {
    .cover-pills { flex-wrap: wrap; justify-content: center; overflow: visible; }
}
.cover-pill {
    flex: none;
    padding: 0.5rem 1.15rem;
    border-radius: 9999px;
    border: 1.5px solid var(--line-strong);
    background: transparent;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ink);
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}
.cover-pill:hover { border-color: var(--ink); background: var(--surface-soft); transform: translateY(-1px); }
.cover-pill.is-on { background: var(--pill-on-bg); border-color: var(--pill-on-bg); color: var(--pill-on-text); }

.cover-stage {
    --card-w: clamp(210px, 64vw, 300px);
    position: relative;
    height: calc(var(--card-w) * 1.3 + 4rem);
    overflow-x: clip;
    touch-action: pan-y;
    user-select: none;
    cursor: grab;
}
.cover-stage:active { cursor: grabbing; }
.cover-stage:focus-visible { outline: 2px solid var(--accent); outline-offset: 4px; border-radius: 28px; }

.cover-card {
    position: absolute;
    top: 2rem;
    left: 50%;
    width: var(--card-w);
    height: calc(var(--card-w) * 1.3);
    margin-left: calc(var(--card-w) / -2);
    border-radius: 28px;
    overflow: hidden;
    background: linear-gradient(160deg, #3a3a42, #1d1d22);
    box-shadow: 0 30px 60px -24px rgba(26, 26, 26, 0.5);
    cursor: pointer;
    transition:
        transform 0.75s cubic-bezier(0.22, 0.8, 0.24, 1),
        opacity 0.5s ease,
        box-shadow 0.5s ease;
}
.cover-card.is-active {
    cursor: default;
    will-change: transform;
    box-shadow: 0 44px 80px -26px var(--accent-shadow), 0 30px 60px -24px rgba(26, 26, 26, 0.55);
}
.cover-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
.cover-empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 5rem;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.35);
}
.cover-shade { position: absolute; inset: 0; background: #14141a; pointer-events: none; transition: opacity 0.6s ease; }

.cover-info {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 3.5rem 1.25rem 1.25rem;
    background: linear-gradient(to top, rgba(12, 12, 18, 0.82), transparent);
    opacity: 0;
    transform: translateY(12px);
    transition: opacity 0.5s ease 0.15s, transform 0.5s ease 0.15s;
    pointer-events: none;
}
.cover-card.is-active .cover-info { opacity: 1; transform: translateY(0); }
.cover-cat {
    display: inline-block;
    margin-bottom: 0.4rem;
    padding: 0.2rem 0.65rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(6px);
    font-size: 0.7rem;
    font-weight: 600;
    color: #fff;
}
.cover-name { font-size: 1.1rem; font-weight: 800; line-height: 1.2; letter-spacing: -0.01em; color: #fff; }

.cover-dots { position: absolute; top: 0.85rem; left: 0; right: 0; display: flex; justify-content: center; gap: 0.35rem; pointer-events: none; }
.cover-dots span { height: 5px; width: 5px; border-radius: 9999px; background: rgba(255, 255, 255, 0.6); transition: width 0.3s ease, background-color 0.3s ease; }
.cover-dots span.is-on { width: 16px; background: #fff; }

.cover-controls { display: flex; align-items: center; justify-content: center; gap: 1.25rem; margin-top: 0.5rem; }
.cover-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.9rem;
    height: 2.9rem;
    border-radius: 9999px;
    border: 1.5px solid var(--line-strong);
    color: var(--ink);
    transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
}
.cover-nav:hover { background: var(--pill-on-bg); border-color: var(--pill-on-bg); color: var(--pill-on-text); transform: scale(1.06); }
.cover-nav:active { transform: scale(0.96); }
.cover-count { min-width: 3.5rem; text-align: center; font-size: 0.85rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--muted); }

/* =====================================================================
   COMPANY UPDATES: a stitched thread of posts
   ===================================================================== */
.thread { position: relative; display: grid; gap: 1.75rem; }
.thread::before {
    content: '';
    position: absolute;
    top: 0.5rem;
    bottom: 0.5rem;
    left: 1.05rem;
    border-left: 2px dashed var(--accent-line);
}
.thread::after {
    content: '';
    position: absolute;
    left: calc(1.05rem - 3px);
    top: 0;
    width: 8px;
    height: 8px;
    border-radius: 9999px;
    background: var(--accent);
    box-shadow: 0 0 12px 3px var(--accent-glow);
    animation: lp-needle 7s ease-in-out infinite;
}
@keyframes lp-needle {
    0% { top: 0; opacity: 0; }
    8% { opacity: 1; }
    92% { opacity: 1; }
    100% { top: calc(100% - 8px); opacity: 0; }
}

.thread-item { position: relative; padding-left: 2.9rem; }
.thread-node {
    position: absolute;
    left: calc(1.05rem + 1px - 9px);
    top: 1.55rem;
    z-index: 1;
    width: 18px;
    height: 18px;
    border-radius: 9999px;
    background: var(--surface);
    border: 3px solid var(--accent);
    box-shadow: 0 0 0 5px var(--bg-1);
}
.thread-node.is-latest { background: var(--accent); animation: lp-ring 2.2s ease-out infinite; }
@keyframes lp-ring {
    0% { box-shadow: 0 0 0 5px var(--bg-1), 0 0 0 5px var(--accent-glow); }
    100% { box-shadow: 0 0 0 5px var(--bg-1), 0 0 0 20px transparent; }
}

.post-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
.post-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lift); }

/* Dashed "stitching" around the text, like a sewn fabric patch */
.post-text { position: relative; }
.post-text::before {
    content: '';
    position: absolute;
    inset: 8px;
    border: 1.5px dashed var(--accent-faint);
    border-radius: 22px;
    pointer-events: none;
}

.post-avatar {
    display: flex;
    flex: none;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 9999px;
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--on-accent);
    background: linear-gradient(135deg, var(--accent-light), var(--accent-dark));
    box-shadow: 0 0 0 3px var(--surface), 0 0 0 5px var(--accent-glow);
}
.post-badge {
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    background: var(--pill-on-bg);
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--pill-on-text);
}
.post-time {
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    background: var(--accent-50);
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--accent-ink);
}
.media-count {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    z-index: 2;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    background: rgba(26, 26, 26, 0.6);
    backdrop-filter: blur(6px);
    font-size: 0.7rem;
    font-weight: 600;
    color: #fff;
}

@media (max-width: 639px) {
    .thread-item { padding-left: 2.5rem; }
}

@media (prefers-reduced-motion: reduce) {
    .anim-down, .page-enter, .lp-blob, .shine-text, .icon-float, .belt-box, .belt-flow, .door-glow, .spark,
    .tree-sway, .truck-bob, .sign-float, .lp-fade, .thread::after, .thread-node.is-latest { animation: none !important; }
    .cover-card, .cover-info, .cover-shade { transition: none; }
    .belt-box { opacity: 1; }
    .shine-text { color: var(--accent); -webkit-text-fill-color: currentColor; background: none; }
}

/* =====================================================================
   OUR SERVICES
   ===================================================================== */
.title-rule {
    display: block;
    width: 56px;
    height: 4px;
    margin: 1.25rem auto 0;
    border-radius: 9999px;
    background: linear-gradient(90deg, var(--accent-light), var(--accent));
    transform-origin: center;
    animation: lp-rule 1s cubic-bezier(0.2, 0.7, 0.2, 1) 0.3s both;
}
@keyframes lp-rule {
    from { transform: scaleX(0); opacity: 0; }
    to { transform: scaleX(1); opacity: 1; }
}

.svc-grid { display: grid; gap: 1.5rem; grid-template-columns: 1fr; }
@media (min-width: 640px) {
    .svc-grid-4, .svc-grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (min-width: 1024px) {
    .svc-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.25rem; }
    .svc-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

.svc-card {
    position: relative;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 28px;
    background: var(--surface);
    box-shadow: var(--shadow);
    transition: transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1), box-shadow 0.35s ease;
}
.svc-card:hover,
.svc-card:focus-within {
    transform: translateY(-8px);
    box-shadow: 0 28px 60px -18px var(--accent-glow), 0 18px 40px rgba(0, 0, 0, 0.1);
}

/* Top visual panel */
.svc-visual {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 168px;
    margin: 10px 10px 0;
    overflow: hidden;
    border-radius: 20px;
    isolation: isolate;
}
.tone-0 .svc-visual { background: linear-gradient(135deg, var(--accent-100) 0%, var(--accent) 100%); }
.tone-1 .svc-visual { background: linear-gradient(135deg, var(--accent-50) 0%, var(--accent-light) 100%); }
.tone-2 .svc-visual { background: linear-gradient(135deg, var(--accent-light) 0%, var(--accent-dark) 100%); }
.tone-3 .svc-visual { background: linear-gradient(135deg, var(--accent-100) 0%, var(--accent-light) 100%); }

/* woven dot texture */
.svc-visual::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    background-image:
        radial-gradient(rgba(255, 255, 255, 0.35) 1.2px, transparent 1.4px),
        repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.07) 0 2px, transparent 2px 10px);
    background-size: 16px 16px, auto;
    transition: transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.svc-card:hover .svc-visual::before { transform: scale(1.12) translate(-6px, -4px); }

/* light sweep on hover */
.svc-visual::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 30%, rgba(255, 255, 255, 0.4) 50%, transparent 70%);
    transform: translateX(-130%);
    pointer-events: none;
}
.svc-card:hover .svc-visual::after { transform: translateX(130%); transition: transform 0.9s ease; }

.svc-orb { position: absolute; z-index: -1; border-radius: 9999px; background: rgba(255, 255, 255, 0.28); }
.svc-orb-a { width: 110px; height: 110px; right: -28px; top: -34px; animation: lp-drift 9s ease-in-out infinite; }
.svc-orb-b { width: 70px; height: 70px; left: -18px; bottom: -22px; background: rgba(255, 255, 255, 0.2); animation: lp-drift 12s ease-in-out -4s infinite reverse; }

.svc-num {
    position: absolute;
    left: 1rem;
    bottom: 0.35rem;
    font-size: 4.25rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -0.05em;
    color: rgba(255, 255, 255, 0.32);
    transition: transform 0.5s cubic-bezier(0.2, 0.7, 0.2, 1), color 0.4s ease;
}
.svc-card:hover .svc-num { transform: translateX(8px); color: rgba(255, 255, 255, 0.5); }

.svc-icon {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 76px;
    height: 76px;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.95);
    color: var(--accent-ink);
    box-shadow: 0 14px 30px -8px var(--accent-shadow);
    animation: lp-bob 4.2s ease-in-out infinite;
    transition: scale 0.5s cubic-bezier(0.2, 0.7, 0.2, 1), rotate 0.5s cubic-bezier(0.2, 0.7, 0.2, 1), border-radius 0.5s ease;
}
.svc-card:hover .svc-icon { scale: 1.1; rotate: -6deg; border-radius: 30px; }
.svc-card:nth-child(even) .svc-icon { animation-delay: -1.7s; }

.svc-tag {
    position: absolute;
    top: 0.85rem;
    right: 0.85rem;
    padding: 0.25rem 0.7rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(6px);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--ink);
}

/* Text body */
.svc-body { display: flex; flex: 1; flex-direction: column; padding: 1.4rem 1.5rem 1.6rem; }
.svc-title { font-size: 1.15rem; font-weight: 800; line-height: 1.25; letter-spacing: -0.015em; color: var(--ink); }
.svc-desc { margin-top: 0.5rem; font-size: 0.9rem; line-height: 1.65; color: var(--muted); }

.svc-features { display: flex; flex-wrap: wrap; gap: 0.45rem; margin-top: 1rem; }
.svc-features li {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.7rem;
    border-radius: 9999px;
    background: var(--accent-50);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--accent-ink);
    transition: background-color 0.25s ease, transform 0.25s ease;
}
.svc-card:hover .svc-features li { background: var(--accent-50-hover); }
.svc-card:hover .svc-features li:nth-child(odd) { transform: translateY(-2px); }

.svc-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    align-self: flex-start;
    margin-top: auto;
    padding-top: 1.25rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--ink);
    transition: color 0.2s ease, gap 0.25s ease;
}
.svc-link svg { transition: transform 0.25s ease; }
.svc-card:hover .svc-link, .svc-link:hover { color: var(--accent-ink); gap: 0.6rem; }
.svc-link:hover svg { transform: translateX(3px); }

/* accent underline that grows across the card bottom */
.svc-bar {
    position: absolute;
    left: 0;
    bottom: 0;
    height: 4px;
    width: 100%;
    background: linear-gradient(90deg, var(--accent-light), var(--accent));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.svc-card:hover .svc-bar, .svc-card:focus-within .svc-bar { transform: scaleX(1); }

/* Call-to-action tile */
.svc-cta {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    overflow: hidden;
    min-height: 320px;
    padding: 2rem;
    border-radius: 28px;
    background: linear-gradient(160deg, #26262c 0%, #14141a 100%);
    box-shadow: var(--shadow);
    transition: transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1), box-shadow 0.35s ease;
}
.svc-cta:hover { transform: translateY(-8px); box-shadow: var(--shadow-lift); }
.svc-cta-glow {
    position: absolute;
    right: -70px;
    top: -70px;
    width: 240px;
    height: 240px;
    border-radius: 9999px;
    background: radial-gradient(circle, var(--accent-glow), transparent 68%);
    animation: lp-pulse 4s ease-in-out infinite;
    pointer-events: none;
}
.svc-cta-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    align-self: flex-start;
    margin-top: 1.5rem;
    padding: 0.8rem 1.5rem;
    border-radius: 9999px;
    background: var(--accent);
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--on-accent);
    box-shadow: 0 10px 24px var(--accent-shadow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.svc-cta-btn:hover { transform: translateY(-2px); box-shadow: 0 16px 30px var(--accent-glow); }
.svc-cta-btn:hover .arrow { transform: translateX(3px); }

@media (prefers-reduced-motion: reduce) {
    .title-rule, .svc-orb, .svc-icon, .svc-cta-glow { animation: none !important; }
    .svc-card, .svc-cta, .svc-visual::before, .svc-num, .svc-bar, .svc-link, .svc-link svg { transition: none; }
    .svc-card:hover, .svc-cta:hover { transform: none; }
    .svc-card:hover .svc-visual::after { transform: translateX(-130%); transition: none; }
}

/* ---------- Small screens ---------- */
@media (max-width: 639px) {
    .stat { padding: 0 0.9rem; }
    .stat-num { font-size: 1.4rem; }
    .stat-label { font-size: 0.72rem; }
    .lp-blob { filter: blur(60px); }
}

@media (max-width: 380px) {
    .hero-title { font-size: 2.9rem; }
    .search-bar { padding-left: 0.75rem; }
}
</style>
