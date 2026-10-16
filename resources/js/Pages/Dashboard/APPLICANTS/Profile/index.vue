<script setup>
// ============================================
// FILE LOCATION: resources/js/Pages/Dashboard/APPLICANTS/Profile/index.vue
// PURPOSE: Applicant profile management with multiple resume uploads - FIXED
// ============================================

import { computed, ref, onBeforeUnmount, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { AlertTriangle } from 'lucide-vue-next'

const props = defineProps({
    applicant: {
        type: Object,
        default: () => ({
            id: null,
            applicant_account_id: null,
            first_name: '',
            middle_name: '',
            last_name: '',
            suffix: '',
            birth_date: '',
            place_of_birth: '',
            citizenship: '',
            religion: '',
            weight: '',
            height: '',
            gender: '',
            civil_status: '',
            email: '',
            phone: '',
            street: '',
            barangay: '',
            city: '',
            province: '',
            zip_code: '',
            highest_education: '',
            school: '',
            course: '',
            graduation_year: '',
            skills: [],
            work_experience: [],
            resumes: [],
            resume_path: null,
            profile_photo_path: null,
            elementary_school: '',
            elementary_year: '',
            high_school: '',
            high_year: '',
            college: '',
            college_year: '',
            vocational: '',
            vocational_year: '',
            special_skills: '',
            emergency_contact: {
                name: '',
                relationship: '',
                phone: '',
                address: '',
            },
        }),
    },
})

// ============================================
// ✅ EDIT MODE STATE
// ============================================
const isEditMode = ref(false)

// ============================================
// ✅ SECTION STATE
// ============================================
const activeSection = ref('personal')

// ✅ Get the correct profile photo URL
const getPhotoUrl = (path) => {
    if (!path) return null
    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path
    }
    if (path.startsWith('/storage/')) {
        return path
    }
    return `/storage/${path}`
}

// ✅ Profile photo preview
const photoPreview = ref(getPhotoUrl(props.applicant?.profile_photo_path))
const originalPhotoUrl = ref(getPhotoUrl(props.applicant?.profile_photo_path))

// ✅ Keep preview in sync when server props refresh (e.g. after save),
// so the uploaded photo stays visible across all steps/processes.
watch(
    () => props.applicant?.profile_photo_path,
    (newPath) => {
        // Don't override a just-selected unsaved blob preview.
        if (photoPreview.value && photoPreview.value.startsWith('blob:') && form.profile_photo) return
        if (photoPreview.value && photoPreview.value.startsWith('blob:')) {
            URL.revokeObjectURL(photoPreview.value)
        }
        const url = getPhotoUrl(newPath)
        photoPreview.value = url
        originalPhotoUrl.value = url
    }
)

// ✅ Get resume file name from path
const getResumeFileName = (path) => {
    if (!path) return ''
    return path.split('/').pop()
}

// ✅ Get resume URL
const getResumeUrl = (path) => {
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path
    }
    if (path.startsWith('/storage/')) {
        return path
    }
    return `/storage/${path}`
}

// ✅ IMPORTANT: Create form with proper data structure
const form = useForm({
    first_name: props.applicant?.first_name || '',
    middle_name: props.applicant?.middle_name || '',
    last_name: props.applicant?.last_name || '',
    suffix: props.applicant?.suffix || '',
    birth_date: props.applicant?.birth_date || '',
    place_of_birth: props.applicant?.place_of_birth || '',
    citizenship: props.applicant?.citizenship || '',
    religion: props.applicant?.religion || '',
    weight: props.applicant?.weight || '',
    height: props.applicant?.height || '',
    gender: props.applicant?.gender || '',
    civil_status: props.applicant?.civil_status || '',
    email: props.applicant?.email || '',
    phone: props.applicant?.phone || '',
    street: props.applicant?.street || '',
    barangay: props.applicant?.barangay || '',
    city: props.applicant?.city || '',
    province: props.applicant?.province || '',
    zip_code: props.applicant?.zip_code || '',
    highest_education: props.applicant?.highest_education || '',
    school: props.applicant?.school || '',
    course: props.applicant?.course || '',
    graduation_year: props.applicant?.graduation_year || '',
    skills: props.applicant?.skills || [],
    work_experience: props.applicant?.work_experience || [],
    profile_photo: null,
    // ✅ IMPORTANT: resumes should be an array to hold File objects
    resumes: [],
    resume_to_delete: [],
    elementary_school: props.applicant?.elementary_school || '',
    elementary_year: props.applicant?.elementary_year || '',
    high_school: props.applicant?.high_school || '',
    high_year: props.applicant?.high_year || '',
    college: props.applicant?.college || '',
    college_year: props.applicant?.college_year || '',
    vocational: props.applicant?.vocational || '',
    vocational_year: props.applicant?.vocational_year || '',
    special_skills: props.applicant?.special_skills || '',
    emergency_contact: {
        name: props.applicant?.emergency_contact?.name || '',
        relationship: props.applicant?.emergency_contact?.relationship || '',
        phone: props.applicant?.emergency_contact?.phone || '',
        address: props.applicant?.emergency_contact?.address || '',
    },
})

const sections = [
    { id: 'personal', label: 'Personal Information', description: 'Basic personal information' },
    { id: 'contact', label: 'Contact Information', description: 'Email and phone number' },
    { id: 'emergency', label: 'Emergency Contact', description: 'Person to contact in an emergency (optional)' },
    { id: 'address', label: 'Address', description: 'Current residential address' },
    { id: 'education', label: 'Education', description: 'Educational background' },
    { id: 'skills', label: 'Skills', description: 'Professional and technical skills' },
    { id: 'experience', label: 'Work Experience', description: 'Previous employment' },
    { id: 'resume', label: 'Resumes', description: 'Upload and manage your resumes' },
]

const fullName = computed(() => {
    return [form.first_name, form.middle_name, form.last_name, form.suffix]
        .filter(Boolean)
        .join(' ')
})

const profileCompletion = computed(() => {
    const fields = [
        form.first_name, form.last_name, form.birth_date, form.gender, form.civil_status,
        form.email, form.phone, form.street, form.barangay, form.city,
        form.province, form.highest_education, form.school, form.course,
    ]
    const completed = fields.filter(value => value !== null && value !== '').length
    return Math.round((completed / fields.length) * 100)
})

function selectSection(section) {
    activeSection.value = section
}

// ============================================
// ✅ MODAL STATES
// ============================================
const modal = ref({
    isOpen: false,
    type: '',
    title: '',
    message: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    onConfirm: null,
})

// ============================================
// ✅ MODAL FUNCTIONS
// ============================================

function showModal(options) {
    modal.value = {
        isOpen: true,
        type: options.type || 'confirm',
        title: options.title || 'Are you sure?',
        message: options.message || '',
        confirmText: options.confirmText || 'Confirm',
        cancelText: options.cancelText || 'Cancel',
        onConfirm: options.onConfirm || null,
    }
}

function closeModal() {
    modal.value.isOpen = false
}

function handleConfirm() {
    if (modal.value.onConfirm) {
        modal.value.onConfirm()
    }
    closeModal()
}

// ============================================
// ✅ EDIT MODE FUNCTIONS
// ============================================

function enableEditMode() {
    showModal({
        type: 'confirm',
        title: 'Enable Edit Mode?',
        message: 'You are about to edit your profile information. Click "Yes" to continue.',
        confirmText: 'Yes, Edit Profile',
        cancelText: 'Cancel',
        onConfirm: () => {
            isEditMode.value = true
            showModal({
                type: 'success',
                title: 'Edit Mode Enabled!',
                message: 'You can now edit your profile information. Don\'t forget to save your changes.',
                confirmText: 'Got it!',
                cancelText: '',
            })
        }
    })
}

function cancelEditMode() {
    showModal({
        type: 'warning',
        title: 'Cancel Editing?',
        message: 'Are you sure you want to cancel editing? Your changes will be lost.',
        confirmText: 'Yes, Cancel',
        cancelText: 'Continue Editing',
        onConfirm: () => {
            // Reset all form fields
            form.first_name = props.applicant?.first_name || ''
            form.middle_name = props.applicant?.middle_name || ''
            form.last_name = props.applicant?.last_name || ''
            form.suffix = props.applicant?.suffix || ''
            form.birth_date = props.applicant?.birth_date || ''
            form.place_of_birth = props.applicant?.place_of_birth || ''
            form.citizenship = props.applicant?.citizenship || ''
            form.religion = props.applicant?.religion || ''
            form.weight = props.applicant?.weight || ''
            form.height = props.applicant?.height || ''
            form.gender = props.applicant?.gender || ''
            form.civil_status = props.applicant?.civil_status || ''
            form.email = props.applicant?.email || ''
            form.phone = props.applicant?.phone || ''
            form.street = props.applicant?.street || ''
            form.barangay = props.applicant?.barangay || ''
            form.city = props.applicant?.city || ''
            form.province = props.applicant?.province || ''
            form.zip_code = props.applicant?.zip_code || ''
            form.highest_education = props.applicant?.highest_education || ''
            form.school = props.applicant?.school || ''
            form.course = props.applicant?.course || ''
            form.graduation_year = props.applicant?.graduation_year || ''
            form.skills = props.applicant?.skills || []
            form.work_experience = props.applicant?.work_experience || []
            form.elementary_school = props.applicant?.elementary_school || ''
            form.elementary_year = props.applicant?.elementary_year || ''
            form.high_school = props.applicant?.high_school || ''
            form.high_year = props.applicant?.high_year || ''
            form.college = props.applicant?.college || ''
            form.college_year = props.applicant?.college_year || ''
            form.vocational = props.applicant?.vocational || ''
            form.vocational_year = props.applicant?.vocational_year || ''
            form.special_skills = props.applicant?.special_skills || ''
            form.emergency_contact = {
                name: props.applicant?.emergency_contact?.name || '',
                relationship: props.applicant?.emergency_contact?.relationship || '',
                phone: props.applicant?.emergency_contact?.phone || '',
                address: props.applicant?.emergency_contact?.address || '',
            }
            // Reset file uploads
            form.resumes = []
            form.resume_to_delete = []
            form.profile_photo = null
            // Restore photo preview to the saved server photo
            if (photoPreview.value && photoPreview.value.startsWith('blob:')) {
                URL.revokeObjectURL(photoPreview.value)
            }
            photoPreview.value = originalPhotoUrl.value || getPhotoUrl(props.applicant?.profile_photo_path)

            isEditMode.value = false
            showModal({
                type: 'success',
                title: 'Changes Cancelled',
                message: 'Your profile changes have been cancelled.',
                confirmText: 'OK',
                cancelText: '',
            })
        }
    })
}

// ============================================
// ✅ CONFIRMATION FUNCTIONS
// ============================================

function confirmSave() {
    showModal({
        type: 'confirm',
        title: 'Save Profile Changes?',
        message: 'Are you sure you want to save your profile changes?',
        confirmText: 'Yes, Save Changes',
        cancelText: 'Cancel',
        onConfirm: saveProfile
    })
}

function guardPhotoClick(event) {
    if (!isEditMode.value) {
        event.preventDefault()
        showModal({
            type: 'warning',
            title: 'Edit Mode Disabled',
            message: 'Please click "Edit Profile" first to make changes.',
            confirmText: 'OK',
            cancelText: '',
        })
    }
}

function confirmPhotoUpload(file) {
    if (!isEditMode.value) {
        showModal({
            type: 'warning',
            title: 'Edit Mode Disabled',
            message: 'Please click "Edit Profile" first to make changes.',
            confirmText: 'OK',
            cancelText: '',
        })
        return
    }

    // Client-side validation (server PHP upload_max_filesize is 2M,
    // so anything bigger would be silently dropped -> compress first).
    if (!file.type.startsWith('image/')) {
        showModal({
            type: 'error',
            title: 'Invalid File',
            message: 'Please select a valid image file (JPG, PNG, GIF, etc.).',
            confirmText: 'OK',
            cancelText: '',
        })
        return
    }

    showModal({
        type: 'confirm',
        title: 'Change Profile Photo?',
        message: `Are you sure you want to change your profile photo to "${file.name}" (${(file.size / 1024).toFixed(0)} KB)?`,
        confirmText: 'Yes, Change Photo',
        cancelText: 'Cancel',
        onConfirm: async () => {
            try {
                const finalFile = await compressImageIfNeeded(file)
                if (photoPreview.value && photoPreview.value.startsWith('blob:')) {
                    URL.revokeObjectURL(photoPreview.value)
                }
                form.profile_photo = finalFile
                photoPreview.value = URL.createObjectURL(finalFile)
                const compressedNote = finalFile.size !== file.size
                    ? ` Compressed to ${(finalFile.size / 1024).toFixed(0)} KB for upload.`
                    : ''
                showModal({
                    type: 'success',
                    title: 'Photo Selected!',
                    message: `Click "Save Changes" to update your profile photo.${compressedNote}`,
                    confirmText: 'OK',
                    cancelText: '',
                })
            } catch (e) {
                console.error('Photo prepare failed:', e)
                showModal({
                    type: 'error',
                    title: 'Photo Error',
                    message: 'Could not prepare that image. Please try a smaller JPG/PNG (max 2MB).',
                    confirmText: 'OK',
                    cancelText: '',
                })
            }
        }
    })
}

// Shrink images down so they fit the server 2MB upload limit.
// Returns the original file if already small, else a compressed JPEG.
function compressImageIfNeeded(file) {
    const MAX_BYTES = 2 * 1024 * 1024
    if (file.size <= MAX_BYTES) return Promise.resolve(file)
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file)
        const img = new Image()
        img.onload = () => {
            URL.revokeObjectURL(url)
            const MAX_DIM = 1280
            let { width, height } = img
            const scale = Math.min(1, MAX_DIM / Math.max(width, height))
            width = Math.round(width * scale)
            height = Math.round(height * scale)
            const canvas = document.createElement('canvas')
            canvas.width = width
            canvas.height = height
            const ctx = canvas.getContext('2d')
            if (!ctx) {
                reject(new Error('No canvas context'))
                return
            }
            ctx.drawImage(img, 0, 0, width, height)
            // Try qualities until under limit, then fall back to smallest.
            const qualities = [0.85, 0.75, 0.65, 0.55]
            let qi = 0
            const attempt = () => {
                canvas.toBlob(
                    (blob) => {
                        if (!blob) {
                            reject(new Error('Compression failed'))
                            return
                        }
                        if (blob.size <= MAX_BYTES || qi >= qualities.length - 1) {
                            const name = file.name.replace(/\.[^.]+$/, '') + '.jpg'
                            resolve(new File([blob], name, { type: 'image/jpeg' }))
                        } else {
                            qi++
                            attempt()
                        }
                    },
                    'image/jpeg',
                    qualities[qi]
                )
            }
            attempt()
        }
        img.onerror = () => {
            URL.revokeObjectURL(url)
            reject(new Error('Could not read image'))
        }
        img.src = url
    })
}

// ✅ FIXED: Resume upload handler - properly handles multiple files
function handleResumeUpload(event) {
    const files = event.target.files
    if (!files || files.length === 0) {
        return
    }
    
    if (!isEditMode.value) {
        showModal({
            type: 'warning',
            title: 'Edit Mode Disabled',
            message: 'Please click "Edit Profile" first to make changes.',
            confirmText: 'OK',
            cancelText: '',
        })
        event.target.value = ''
        return
    }
    
    // Show confirmation before adding files
    const fileNames = Array.from(files).map(f => f.name).join(', ')
    showModal({
        type: 'confirm',
        title: 'Upload Resumes?',
        message: `Are you sure you want to upload ${files.length} file(s): ${fileNames}?`,
        confirmText: 'Yes, Upload',
        cancelText: 'Cancel',
        onConfirm: () => {
            // ✅ Add files to form.resumes array
            for (const file of files) {
                form.resumes.push(file)
            }
            // Reset input
            event.target.value = ''
            showModal({
                type: 'success',
                title: 'Resumes Added!',
                message: `${files.length} resume(s) will be uploaded when you save your profile.`,
                confirmText: 'OK',
                cancelText: '',
            })
        },
        onCancel: () => {
            event.target.value = ''
        }
    })
}

// ✅ FIXED: Remove pending resume from upload queue
function removePendingResume(index) {
    const fileName = form.resumes[index]?.name || 'file'
    showModal({
        type: 'warning',
        title: 'Remove File?',
        message: `Are you sure you want to remove "${fileName}" from the upload queue?`,
        confirmText: 'Yes, Remove',
        cancelText: 'Cancel',
        onConfirm: () => {
            form.resumes.splice(index, 1)
        }
    })
}

// ✅ FIXED: Delete existing resume
function deleteExistingResume(index) {
    const resumeName = getResumeFileName(props.applicant.resumes[index])
    showModal({
        type: 'warning',
        title: 'Delete Resume?',
        message: `Are you sure you want to delete "${resumeName}"?`,
        confirmText: 'Yes, Delete',
        cancelText: 'Cancel',
        onConfirm: () => {
            const path = props.applicant.resumes[index]
            // Add to delete list
            if (!form.resume_to_delete) {
                form.resume_to_delete = []
            }
            form.resume_to_delete.push(path)
            // Remove from displayed list
            props.applicant.resumes.splice(index, 1)
            showModal({
                type: 'success',
                title: 'Resume Deleted!',
                message: 'The resume will be removed when you save your profile.',
                confirmText: 'OK',
                cancelText: '',
            })
        }
    })
}

function confirmAddSkill() {
    if (!isEditMode.value) {
        showModal({
            type: 'warning',
            title: 'Edit Mode Disabled',
            message: 'Please click "Edit Profile" first to make changes.',
            confirmText: 'OK',
            cancelText: '',
        })
        return
    }
    
    showModal({
        type: 'confirm',
        title: 'Add Skill?',
        message: 'Are you sure you want to add a new skill?',
        confirmText: 'Yes, Add Skill',
        cancelText: 'Cancel',
        onConfirm: () => {
            form.skills.push('')
        }
    })
}

function confirmRemoveSkill(index) {
    if (!isEditMode.value) {
        showModal({
            type: 'warning',
            title: 'Edit Mode Disabled',
            message: 'Please click "Edit Profile" first to make changes.',
            confirmText: 'OK',
            cancelText: '',
        })
        return
    }
    
    const skillName = form.skills[index] || 'skill'
    showModal({
        type: 'warning',
        title: 'Remove Skill?',
        message: `Are you sure you want to remove "${skillName}"?`,
        confirmText: 'Yes, Remove',
        cancelText: 'Cancel',
        onConfirm: () => {
            form.skills.splice(index, 1)
            showModal({
                type: 'success',
                title: 'Skill Removed!',
                message: 'The skill has been removed.',
                confirmText: 'OK',
                cancelText: '',
            })
        }
    })
}

function confirmAddExperience() {
    if (!isEditMode.value) {
        showModal({
            type: 'warning',
            title: 'Edit Mode Disabled',
            message: 'Please click "Edit Profile" first to make changes.',
            confirmText: 'OK',
            cancelText: '',
        })
        return
    }
    
    showModal({
        type: 'confirm',
        title: 'Add Work Experience?',
        message: 'Are you sure you want to add new work experience?',
        confirmText: 'Yes, Add Experience',
        cancelText: 'Cancel',
        onConfirm: () => {
            form.work_experience.push({
                company: '',
                position: '',
                start_date: '',
                end_date: '',
                responsibilities: '',
            })
        }
    })
}

function confirmRemoveExperience(index) {
    if (!isEditMode.value) {
        showModal({
            type: 'warning',
            title: 'Edit Mode Disabled',
            message: 'Please click "Edit Profile" first to make changes.',
            confirmText: 'OK',
            cancelText: '',
        })
        return
    }
    
    const exp = form.work_experience[index] || {}
    const company = exp.company || 'this experience'
    showModal({
        type: 'warning',
        title: 'Remove Work Experience?',
        message: `Are you sure you want to remove "${company}"?`,
        confirmText: 'Yes, Remove',
        cancelText: 'Cancel',
        onConfirm: () => {
            form.work_experience.splice(index, 1)
            showModal({
                type: 'success',
                title: 'Experience Removed!',
                message: 'The work experience has been removed.',
                confirmText: 'OK',
                cancelText: '',
            })
        }
    })
}

function handlePhotoUpload(event) {
    const file = event.target.files[0]
    if (!file) return
    confirmPhotoUpload(file)
    event.target.value = ''
}

function addSkill() {
    confirmAddSkill()
}

function removeSkill(index) {
    confirmRemoveSkill(index)
}

function addExperience() {
    confirmAddExperience()
}

function removeExperience(index) {
    confirmRemoveExperience(index)
}

// ✅ FIXED: Save profile with proper file handling
function saveProfile() {
    // Ensure resume_to_delete is an array
    if (!form.resume_to_delete) {
        form.resume_to_delete = []
    }
    
    const hadPhotoToUpload = !!form.profile_photo
    const photoName = form.profile_photo?.name || null
    const photoSizeKb = form.profile_photo ? Math.round(form.profile_photo.size / 1024) : 0

    // Log what's being sent
    console.log('Saving profile with:', {
        resumes: form.resumes.length + ' files',
        resume_to_delete: form.resume_to_delete.length + ' files',
        has_profile_photo: hadPhotoToUpload,
        photo_name: photoName,
        photo_size_kb: photoSizeKb,
    })

    // ✅ IMPORTANT: Use forceFormData: true to handle file uploads properly
    form.post(route('applicant.profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (page) => {
            // Inertia reloads props after back() redirect; prefer fresh server value,
            // fall back to keeping the selected blob preview until watcher syncs.
            const freshPath = page?.props?.applicant?.profile_photo_path || props.applicant?.profile_photo_path
            console.log('Profile save success. Server photo path:', freshPath)
            if (hadPhotoToUpload && !freshPath) {
                // Photo was sent but server has no path -> silent drop (too large, etc.)
                showModal({
                    type: 'warning',
                    title: 'Photo May Not Have Saved',
                    message: `Your other changes were saved, but "${photoName || 'photo'}" (${photoSizeKb} KB) has no stored path on the server. Try a smaller JPG/PNG under 2MB, then save again.`,
                    confirmText: 'OK',
                    cancelText: '',
                })
            }
            if (freshPath) {
                if (photoPreview.value && photoPreview.value.startsWith('blob:') && !form.profile_photo) {
                    URL.revokeObjectURL(photoPreview.value)
                }
                if (!form.profile_photo) {
                    photoPreview.value = getPhotoUrl(freshPath)
                    originalPhotoUrl.value = photoPreview.value
                } else {
                    // Keep blob preview; watcher will swap to server URL on prop refresh.
                    originalPhotoUrl.value = getPhotoUrl(freshPath)
                }
            } else if (!form.profile_photo && photoPreview.value && photoPreview.value.startsWith('blob:')) {
                URL.revokeObjectURL(photoPreview.value)
                photoPreview.value = null
            }
            isEditMode.value = false
            form.profile_photo = null
            form.resumes = []
            form.resume_to_delete = []
            if (!(hadPhotoToUpload && !freshPath)) {
                showModal({
                    type: 'success',
                    title: 'Profile Updated!',
                    message: hadPhotoToUpload
                        ? 'Your profile and photo have been saved successfully.'
                        : 'Your profile has been saved successfully.',
                    confirmText: 'OK',
                    cancelText: '',
                })
            }
        },
        onError: (errors) => {
            console.error('Save errors:', errors)
            const errorMsg = Object.values(errors)[0] || 'Failed to update profile. Please try again.'
            showModal({
                type: 'error',
                title: 'Update Failed!',
                message: errorMsg,
                confirmText: 'OK',
                cancelText: '',
            })
        },
    })
}

// ✅ Clean up object URLs on component unmount
onBeforeUnmount(() => {
    if (photoPreview.value && photoPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(photoPreview.value)
    }
})

// ============================================
// ✅ HELPER: Check if field is disabled
// ============================================
const isDisabled = computed(() => !isEditMode.value)
</script>

<template>
    <Head title="My Profile - Monti ERP" />

    <AuthenticatedLayout>
        <div class="flex flex-col">
            <div class="flex-1 text-slate-900 dark:text-slate-100 relative flex flex-col overflow-hidden">

                <!-- HEADER -->
                <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shrink-0">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                            My <span class="text-blue-600">Profile</span>
                        </h1>
                        <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Manage your personal information and professional profile.</p>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
                        <span
                            v-if="isEditMode"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 text-xs font-bold rounded-full"
                        >
                            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                            Editing Mode
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 text-xs font-bold rounded-full"
                        >
                            View Mode
                        </span>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid lg:grid-cols-4 gap-4 flex-1 min-h-0">
                    
                    <!-- LEFT SIDEBAR -->
                    <aside class="lg:col-span-1">
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden p-5">
                            <div class="text-center">
                                <div class="relative mx-auto h-28 w-28">
                                    <img
                                        v-if="photoPreview"
                                        :src="photoPreview"
                                        alt="Profile photo"
                                        class="h-28 w-28 rounded-2xl object-cover ring-4 ring-slate-100 dark:ring-slate-700"
                                        @error="photoPreview = null"
                                    />
                                    <div
                                        v-else
                                        class="h-28 w-28 rounded-2xl bg-slate-100 dark:bg-slate-700 border-2 border-dashed border-slate-300 dark:border-slate-600 flex items-center justify-center text-3xl font-bold text-slate-400"
                                    >
                                        {{ form.first_name?.charAt(0)?.toUpperCase() || '?' }}
                                    </div>
                                    <label
                                        class="absolute bottom-0 right-0 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full text-white shadow-md transition"
                                        :class="isEditMode ? 'bg-blue-600 hover:bg-blue-700' : 'bg-slate-300 dark:bg-slate-600 cursor-not-allowed'"
                                        :title="isEditMode ? 'Change profile photo' : 'Enable edit mode first'"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="hidden"
                                            @click="guardPhotoClick"
                                            @change="handlePhotoUpload"
                                        />
                                    </label>
                                </div>
                                <h2 class="text-lg font-bold text-slate-900 dark:text-white mt-3">
                                    {{ fullName || 'Your Name' }}
                                </h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Applicant</p>
                                <p v-if="form.profile_photo" class="mt-1 text-[11px] font-bold text-amber-600 dark:text-amber-400">
                                    New photo selected — click Save Now to upload.
                                </p>
                                <p v-else-if="!photoPreview" class="mt-1 text-[11px] text-slate-400">
                                    No profile photo yet — click + to upload.
                                </p>
                                <span
                                    v-if="isEditMode"
                                    class="mt-2 inline-block px-3 py-1 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 text-[10px] font-bold rounded-full"
                                >
                                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                    Editing Enabled
                                </span>
                            </div>

                            <div class="mt-6">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Profile Completion</span>
                                    <span class="text-xs font-bold text-blue-600 dark:text-blue-400">{{ profileCompletion }}%</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                                    <div
                                        class="h-full rounded-full bg-blue-600 transition-all"
                                        :style="{ width: profileCompletion + '%' }"
                                    ></div>
                                </div>
                                <p class="mt-2 text-[10px] text-slate-400">
                                    Complete your profile to improve your application.
                                </p>
                            </div>

                            <nav class="mt-6 space-y-0.5">
                                <button
                                    v-for="section in sections"
                                    :key="section.id"
                                    type="button"
                                    @click="selectSection(section.id)"
                                    class="w-full rounded-lg px-4 py-2.5 text-left transition-all duration-200 text-xs flex items-center gap-3"
                                    :class="
                                        activeSection === section.id
                                            ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400 border-l-2 border-blue-600 dark:border-blue-500'
                                            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white'
                                    "
                                >
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" :class="activeSection === section.id ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400'">
                                        <path v-if="section.id === 'personal'" stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        <path v-if="section.id === 'contact'" stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        <path v-if="section.id === 'emergency'" stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                        <path v-if="section.id === 'address'" stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path v-if="section.id === 'education'" stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                                        <path v-if="section.id === 'skills'" stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                        <path v-if="section.id === 'experience'" stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        <path v-if="section.id === 'resume'" stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                    <div>
                                        <div class="font-semibold">{{ section.label }}</div>
                                        <div class="text-[10px]" :class="activeSection === section.id ? 'text-blue-600/70 dark:text-blue-400/70' : 'text-slate-400'">
                                            {{ section.description }}
                                        </div>
                                    </div>
                                </button>
                            </nav>
                        </div>
                    </aside>

                    <!-- RIGHT CONTENT -->
                    <section class="lg:col-span-3">
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                            <!-- Edit Mode Notice Banner -->
                            <div
                                v-if="isEditMode"
                                class="mx-5 mt-5 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4 flex flex-wrap items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-3">
                                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                    <div>
                                        <p class="text-sm font-bold text-blue-700 dark:text-blue-400">Edit Mode Enabled</p>
                                        <p class="text-xs text-blue-600/80 dark:text-blue-400/80">You can now edit all fields. Don't forget to save your changes.</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="cancelEditMode"
                                        class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-bold transition active:scale-95"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        @click="confirmSave"
                                        :disabled="form.processing"
                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-blue-500/30 active:scale-95 disabled:opacity-50"
                                    >
                                        {{ form.processing ? 'Saving...' : 'Save Now' }}
                                    </button>
                                </div>
                            </div>

                            <div
                                v-else
                                class="mx-5 mt-5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-4 flex flex-wrap items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-3">
                                    <svg class="w-6 h-6 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <div>
                                        <p class="text-sm font-bold text-slate-700 dark:text-slate-200">View Mode</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Click the button below to make changes to your information.</p>
                                    </div>
                                </div>
                                <button
                                    @click="enableEditMode"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95"
                                >
                                    Edit Profile
                                </button>
                            </div>

                            <form @submit.prevent="confirmSave" class="p-5">
                                <!-- PERSONAL INFORMATION -->
                                <div v-if="activeSection === 'personal'">
                                    <div class="border-b border-slate-100 dark:border-slate-700 pb-4 flex items-center gap-3">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <div>
                                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Personal Information</h2>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Provide your basic personal information.</p>
                                        </div>
                                    </div>
                                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">First Name</label>
                                            <input 
                                                v-model="form.first_name" 
                                                type="text" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                            <p v-if="form.errors.first_name" class="text-xs text-red-600 dark:text-red-400 mt-1">
                                                {{ form.errors.first_name }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Middle Name</label>
                                            <input 
                                                v-model="form.middle_name" 
                                                type="text" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Last Name</label>
                                            <input 
                                                v-model="form.last_name" 
                                                type="text" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                            <p v-if="form.errors.last_name" class="text-xs text-red-600 dark:text-red-400 mt-1">
                                                {{ form.errors.last_name }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Suffix</label>
                                            <select 
                                                v-model="form.suffix" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            >
                                                <option value="">None</option>
                                                <option value="Jr.">Jr.</option>
                                                <option value="Sr.">Sr.</option>
                                                <option value="II">II</option>
                                                <option value="III">III</option>
                                                <option value="IV">IV</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Date of Birth</label>
                                            <input 
                                                v-model="form.birth_date" 
                                                type="date" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Gender</label>
                                            <select 
                                                v-model="form.gender" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            >
                                                <option value="">Select gender</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Civil Status</label>
                                            <select 
                                                v-model="form.civil_status" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            >
                                                <option value="">Select civil status</option>
                                                <option value="Single">Single</option>
                                                <option value="Married">Married</option>
                                                <option value="Widowed">Widowed</option>
                                                <option value="Separated">Separated</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Place of Birth</label>
                                            <input
                                                v-model="form.place_of_birth"
                                                type="text"
                                                placeholder="e.g., Imus, Cavite"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Citizenship</label>
                                            <input
                                                v-model="form.citizenship"
                                                type="text"
                                                placeholder="e.g., Filipino"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Religion</label>
                                            <input
                                                v-model="form.religion"
                                                type="text"
                                                placeholder="e.g., Roman Catholic"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Weight (kg)</label>
                                            <input
                                                v-model="form.weight"
                                                type="number"
                                                min="20"
                                                max="300"
                                                step="0.01"
                                                placeholder="e.g., 65"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Height (cm)</label>
                                            <input
                                                v-model="form.height"
                                                type="number"
                                                min="100"
                                                max="250"
                                                step="0.01"
                                                placeholder="e.g., 170"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- CONTACT -->
                                <div v-if="activeSection === 'contact'">
                                    <div class="border-b border-slate-100 dark:border-slate-700 pb-4 flex items-center gap-3">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        <div>
                                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Contact Information</h2>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Keep your contact information updated.</p>
                                        </div>
                                    </div>
                                    <div class="mt-5 space-y-4">
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Email Address</label>
                                            <input
                                                v-model="form.email"
                                                type="email"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                                placeholder="your-email@example.com"
                                            />
                                            <p class="mt-1 text-[10px] text-blue-600 dark:text-blue-400">
                                                Email is synced with your account
                                            </p>
                                            <p v-if="form.errors.email" class="text-xs text-red-600 dark:text-red-400 mt-1">
                                                {{ form.errors.email }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Phone Number</label>
                                            <input
                                                v-model="form.phone"
                                                type="tel"
                                                placeholder="+63 9XX XXX XXXX"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- EMERGENCY CONTACT -->
                                <div v-if="activeSection === 'emergency'">
                                    <div class="border-b border-slate-100 dark:border-slate-700 pb-4 flex items-center gap-3">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                        </svg>
                                        <div>
                                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Emergency Contact</h2>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Person to contact in case of an emergency.</p>
                                        </div>
                                    </div>
                                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Name</label>
                                            <input
                                                v-model="form.emergency_contact.name"
                                                type="text"
                                                placeholder="Emergency contact name"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                            <p v-if="form.errors['emergency_contact.name']" class="text-xs text-red-600 dark:text-red-400 mt-1">
                                                {{ form.errors['emergency_contact.name'] }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Relationship</label>
                                            <input
                                                v-model="form.emergency_contact.relationship"
                                                type="text"
                                                placeholder="e.g., Spouse, Parent, Sibling"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Phone Number</label>
                                            <input
                                                v-model="form.emergency_contact.phone"
                                                type="tel"
                                                placeholder="+63 9XX XXX XXXX"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Address</label>
                                            <input
                                                v-model="form.emergency_contact.address"
                                                type="text"
                                                placeholder="Emergency contact address"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                    </div>
                                    <p class="mt-3 text-[11px] text-slate-400">
                                        Optional: you can leave these fields blank and still save your profile and apply for jobs.
                                    </p>
                                </div>

                                <!-- ADDRESS -->
                                <div v-if="activeSection === 'address'">
                                    <div class="border-b border-slate-100 dark:border-slate-700 pb-4 flex items-center gap-3">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <div>
                                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Address</h2>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Provide your current residential address.</p>
                                        </div>
                                    </div>
                                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="md:col-span-2">
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Street / House Number</label>
                                            <input 
                                                v-model="form.street" 
                                                type="text" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Barangay</label>
                                            <input 
                                                v-model="form.barangay" 
                                                type="text" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">City / Municipality</label>
                                            <input 
                                                v-model="form.city" 
                                                type="text" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Province</label>
                                            <input 
                                                v-model="form.province" 
                                                type="text" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">ZIP Code</label>
                                            <input 
                                                v-model="form.zip_code" 
                                                type="text" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- EDUCATION -->
                                <div v-if="activeSection === 'education'">
                                    <div class="border-b border-slate-100 dark:border-slate-700 pb-4 flex items-center gap-3">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                                        </svg>
                                        <div>
                                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Education</h2>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Add your complete educational background.</p>
                                        </div>
                                    </div>
                                    <div class="mt-5 space-y-4">
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Highest Educational Attainment</label>
                                            <select 
                                                v-model="form.highest_education" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            >
                                                <option value="">Select education level</option>
                                                <option value="High School">High School</option>
                                                <option value="Vocational">Vocational</option>
                                                <option value="Associate Degree">Associate Degree</option>
                                                <option value="Bachelor's Degree">Bachelor's Degree</option>
                                                <option value="Master's Degree">Master's Degree</option>
                                                <option value="Doctorate">Doctorate</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">School / University</label>
                                            <input 
                                                v-model="form.school" 
                                                type="text" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                                placeholder="Name of school or university"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Course / Program</label>
                                            <input 
                                                v-model="form.course" 
                                                type="text" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                                placeholder="Course or program name"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Graduation Year</label>
                                            <input
                                                v-model="form.graduation_year"
                                                type="number"
                                                min="1950"
                                                :max="new Date().getFullYear() + 10"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                                placeholder="YYYY"
                                            />
                                        </div>
                                        <div class="border-t border-slate-100 dark:border-slate-700 my-4"></div>

                                        <!-- Elementary -->
                                        <div>
                                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-2">Elementary</h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">School</label>
                                                    <input 
                                                        v-model="form.elementary_school" 
                                                        type="text" 
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                        placeholder="Elementary school name"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Year Graduated</label>
                                                    <input 
                                                        v-model="form.elementary_year" 
                                                        type="text" 
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                        placeholder="YYYY"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- High School -->
                                        <div>
                                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-2">High School</h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">School</label>
                                                    <input 
                                                        v-model="form.high_school" 
                                                        type="text" 
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                        placeholder="High school name"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Year Graduated</label>
                                                    <input 
                                                        v-model="form.high_year" 
                                                        type="text" 
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                        placeholder="YYYY"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- College -->
                                        <div>
                                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-2">College</h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Course & School</label>
                                                    <input 
                                                        v-model="form.college" 
                                                        type="text" 
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                        placeholder="Course & School name"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Year Graduated</label>
                                                    <input 
                                                        v-model="form.college_year" 
                                                        type="text" 
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                        placeholder="YYYY"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Vocational -->
                                        <div>
                                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-2">Vocational</h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Course & School</label>
                                                    <input 
                                                        v-model="form.vocational" 
                                                        type="text" 
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                        placeholder="Course & School name"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Year Graduated</label>
                                                    <input 
                                                        v-model="form.vocational_year" 
                                                        type="text" 
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                        placeholder="YYYY"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Special Skills -->
                                        <div>
                                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Special Skills</label>
                                            <input 
                                                v-model="form.special_skills" 
                                                type="text" 
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                                placeholder="e.g., Microsoft Office, Sewing, Programming"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- SKILLS -->
                                <div v-if="activeSection === 'skills'">
                                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                            </svg>
                                            <div>
                                                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Skills</h2>
                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Add skills that represent your qualifications.</p>
                                            </div>
                                        </div>
                                        <button
                                            v-if="isEditMode"
                                            type="button"
                                            @click="addSkill"
                                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-sm active:scale-95"
                                        >
                                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add Skill
                                        </button>
                                    </div>
                                    <div class="mt-5 space-y-3">
                                        <div
                                            v-for="(skill, index) in form.skills"
                                            :key="index"
                                            class="flex gap-3"
                                        >
                                            <input
                                                v-model="form.skills[index]"
                                                type="text"
                                                placeholder="e.g. Laravel"
                                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                :disabled="!isEditMode"
                                            />
                                            <button
                                                v-if="isEditMode"
                                                type="button"
                                                @click="removeSkill(index)"
                                                class="px-4 py-2 border border-slate-200 dark:border-slate-700 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 rounded-xl text-xs font-bold transition active:scale-95"
                                            >
                                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                Remove
                                            </button>
                                        </div>
                                        <div
                                            v-if="form.skills.length === 0"
                                            class="rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-8 text-center"
                                        >
                                            <p class="text-sm text-slate-400">No skills added yet.</p>
                                            <button
                                                v-if="isEditMode"
                                                type="button"
                                                @click="addSkill"
                                                class="mt-3 text-sm font-bold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors"
                                            >
                                                Add your first skill
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- WORK EXPERIENCE -->
                                <div v-if="activeSection === 'experience'">
                                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <div>
                                                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Work Experience</h2>
                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Add your previous employment experience.</p>
                                            </div>
                                        </div>
                                        <button
                                            v-if="isEditMode"
                                            type="button"
                                            @click="addExperience"
                                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-sm active:scale-95"
                                        >
                                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add Experience
                                        </button>
                                    </div>
                                    <div class="mt-5 space-y-5">
                                        <div
                                            v-for="(experience, index) in form.work_experience"
                                            :key="index"
                                            class="rounded-xl border border-slate-200 dark:border-slate-700 p-5 bg-slate-50 dark:bg-slate-900"
                                        >
                                            <div class="mb-4 flex items-center justify-between">
                                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Experience {{ index + 1 }}</h3>
                                                <button
                                                    v-if="isEditMode"
                                                    type="button"
                                                    @click="removeExperience(index)"
                                                    class="text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition-colors flex items-center gap-1"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    Remove
                                                </button>
                                            </div>
                                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div>
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Company</label>
                                                    <input 
                                                        v-model="experience.company" 
                                                        type="text" 
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Position</label>
                                                    <input 
                                                        v-model="experience.position" 
                                                        type="text" 
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Start Date</label>
                                                    <input 
                                                        v-model="experience.start_date" 
                                                        type="date" 
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">End Date</label>
                                                    <input 
                                                        v-model="experience.end_date" 
                                                        type="date" 
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                    />
                                                </div>
                                                <div class="md:col-span-2">
                                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Responsibilities</label>
                                                    <textarea
                                                        v-model="experience.responsibilities"
                                                        rows="3"
                                                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-70"
                                                        :class="!isEditMode ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''"
                                                        :disabled="!isEditMode"
                                                        placeholder="Describe your main responsibilities..."
                                                    ></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-if="form.work_experience.length === 0"
                                            class="rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-8 text-center"
                                        >
                                            <p class="text-sm text-slate-400">No work experience added.</p>
                                            <button
                                                v-if="isEditMode"
                                                type="button"
                                                @click="addExperience"
                                                class="mt-3 text-sm font-bold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors"
                                            >
                                                Add work experience
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- RESUMES - FIXED MULTIPLE UPLOAD -->
                                <div v-if="activeSection === 'resume'">
                                    <div class="border-b border-slate-100 dark:border-slate-700 pb-4 flex items-center gap-3">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                        <div>
                                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Resumes</h2>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Upload multiple resumes (PDF, DOC, DOCX).</p>
                                        </div>
                                    </div>
                                    <div class="mt-5">
                                        <!-- ✅ FIXED: Upload Area with proper multiple attribute -->
                                        <label
                                            class="block cursor-pointer rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-600 p-8 text-center transition"
                                            :class="isEditMode ? 'hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/10' : 'opacity-50'"
                                        >
                                            <svg class="w-12 h-12 mx-auto text-slate-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                            </svg>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                                {{ isEditMode ? 'Upload your resumes' : 'Enable edit mode to upload' }}
                                            </p>
                                            <p class="mt-1 text-xs text-slate-400">PDF, DOC, or DOCX (Max 5MB each)</p>
                                            <p class="text-xs text-slate-400 mt-1">You can upload multiple files</p>
                                            <input
                                                type="file"
                                                accept=".pdf,.doc,.docx"
                                                class="hidden"
                                                :disabled="!isEditMode"
                                                multiple
                                                @change="handleResumeUpload"
                                            />
                                        </label>

                                        <!-- ✅ Current Resumes -->
                                        <div v-if="props.applicant.resumes && props.applicant.resumes.length > 0" class="mt-4">
                                            <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Current Resumes ({{ props.applicant.resumes.length }})</h4>
                                            <div
                                                v-for="(resume, index) in props.applicant.resumes"
                                                :key="'existing-' + index"
                                                class="flex items-center justify-between rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 p-3 mb-2"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 font-bold text-sm">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ getResumeFileName(resume) }}</p>
                                                        <a
                                                            :href="getResumeUrl(resume)"
                                                            target="_blank"
                                                            class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors"
                                                        >
                                                            View Resume →
                                                        </a>
                                                    </div>
                                                </div>
                                                <button
                                                    v-if="isEditMode"
                                                    type="button"
                                                    @click="deleteExistingResume(index)"
                                                    class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                                    title="Delete this resume"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- ✅ Pending Uploads -->
                                        <div v-if="form.resumes && form.resumes.length > 0" class="mt-4">
                                            <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Pending Uploads ({{ form.resumes.length }})</h4>
                                            <div
                                                v-for="(file, index) in form.resumes"
                                                :key="'new-' + index"
                                                class="flex items-center justify-between rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-3 mb-2"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 font-bold text-sm">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ file.name }}</p>
                                                        <p class="text-xs text-slate-400">{{ (file.size / 1024).toFixed(0) }} KB - Pending upload</p>
                                                    </div>
                                                </div>
                                                <button
                                                    v-if="isEditMode"
                                                    type="button"
                                                    @click="removePendingResume(index)"
                                                    class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                                    title="Remove from upload queue"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <p class="text-xs text-amber-700 dark:text-amber-400 mt-2 flex items-center gap-1">
                                                <AlertTriangle class="h-3.5 w-3.5 flex-shrink-0" />
                                                <span>These files will be uploaded when you click "Save Now"</span>
                                            </p>
                                        </div>

                                        <!-- No Resumes -->
                                        <div v-if="(!props.applicant.resumes || props.applicant.resumes.length === 0) && (!form.resumes || form.resumes.length === 0)" class="text-center py-8">
                                            <p class="text-sm text-slate-400">No resumes uploaded yet.</p>
                                            <p v-if="isEditMode" class="text-xs text-slate-500 dark:text-slate-400 mt-1">Upload your resumes using the button above.</p>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>

                <!-- Footer -->
                <div class="mt-2 text-xs text-slate-400 shrink-0">
                    © 2026 Monti Textile Manufacturing Corp. All rights reserved.
                </div>
            </div>
        </div>

        <!-- MODAL -->
        <div
            v-if="modal.isOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="closeModal"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 max-w-md w-full overflow-hidden shadow-2xl animate-modalIn">
                <div class="p-6 border-b border-slate-100 dark:border-slate-700" :class="{
                    'bg-blue-50 dark:bg-blue-900/20': modal.type === 'confirm',
                    'bg-emerald-50 dark:bg-emerald-900/20': modal.type === 'success',
                    'bg-red-50 dark:bg-red-900/20': modal.type === 'error',
                    'bg-amber-50 dark:bg-amber-900/20': modal.type === 'warning',
                }">
                    <div class="flex items-center gap-3">
                        <div
                            v-if="modal.type === 'confirm'"
                            class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0"
                        >
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div
                            v-if="modal.type === 'success'"
                            class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0"
                        >
                            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div
                            v-if="modal.type === 'error'"
                            class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0"
                        >
                            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div
                            v-if="modal.type === 'warning'"
                            class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0"
                        >
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>

                        <h3 class="text-lg font-bold" :class="{
                            'text-blue-700 dark:text-blue-400': modal.type === 'confirm',
                            'text-emerald-700 dark:text-emerald-400': modal.type === 'success',
                            'text-red-700 dark:text-red-400': modal.type === 'error',
                            'text-amber-700 dark:text-amber-400': modal.type === 'warning',
                        }">{{ modal.title }}</h3>
                    </div>
                </div>

                <div class="p-6">
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">{{ modal.message }}</p>
                </div>

                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3">
                    <button
                        v-if="modal.cancelText"
                        @click="closeModal"
                        class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-bold transition active:scale-95"
                    >
                        {{ modal.cancelText }}
                    </button>
                    <button
                        @click="handleConfirm"
                        class="px-4 py-2 text-white rounded-xl text-sm font-bold transition shadow-sm active:scale-95"
                        :class="{
                            'bg-blue-600 hover:bg-blue-700 shadow-blue-500/30': modal.type === 'confirm',
                            'bg-emerald-600 hover:bg-emerald-700': modal.type === 'success',
                            'bg-red-600 hover:bg-red-700': modal.type === 'error',
                            'bg-amber-600 hover:bg-amber-700': modal.type === 'warning',
                        }"
                    >
                        {{ modal.confirmText }}
                    </button>
                </div>
            </div>
        </div>

        <!-- SAVING OVERLAY: simple blur while profile save is in progress -->
        <div
            v-if="form.processing"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-white/60 dark:bg-slate-900/60 backdrop-blur-md"
            aria-live="polite"
        >
            <div class="flex flex-col items-center gap-3">
                <div class="w-10 h-10 rounded-full border-4 border-slate-200 dark:border-slate-700 border-t-blue-600 animate-spin"></div>
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Saving changes...</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.animate-modalIn {
    animation: modalIn 0.2s ease-out;
}

@keyframes modalIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
</style>