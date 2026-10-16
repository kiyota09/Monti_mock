<template>
  <AuthenticatedLayout>
    <div class="flex-1 p-8 text-slate-900 font-sans min-w-0 relative">
      
      <!-- Professional Header -->
      <header class="mb-8 relative">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
          <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3">
              <Link :href="route('hrm.applications.index')" class="hover:text-slate-700 transition-colors flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Forms
              </Link>
              <svg class="w-3 h-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
              <span class="text-slate-800 font-semibold">Application Form</span>
            </nav>
            <div>
              <h1 class="text-3xl font-bold tracking-tight text-slate-900">Application Form</h1>
              <p class="text-sm text-slate-500 mt-1">View and manage applicant submissions</p>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Content -->
      <div class="bg-white rounded-2xl border border-slate-200/60 shadow-lg p-6">
        <div class="max-w-5xl mx-auto">
          <div class="text-center mb-10">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
              Careers Application
            </h1>
            <p class="mt-3 text-slate-600 text-base max-w-2xl mx-auto">
              Fill out the details below to apply for a position. All applications are securely processed by our human resources department.
            </p>
          </div>

          <form @submit.prevent="submitForm" class="space-y-8">
            <!-- Personal Identity -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div class="md:col-span-3">
                <h3 class="text-sm font-black uppercase tracking-widest text-blue-600 border-b border-slate-200 pb-2">
                  Personal Identity
                </h3>
              </div>

              <!-- Profile Photo -->
              <div>
                <InputLabel for="image" value="Profile Photo (Optional)" class="text-slate-700 font-semibold" />
                <div class="relative h-32 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 hover:border-blue-400/50 transition-all group overflow-hidden">
                  <template v-if="!form.image">
                    <Upload class="h-5 w-5 text-slate-400 group-hover:text-blue-500 transition-colors mb-2 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
                    <input type="file" @change="handleImageUpload" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*" />
                  </template>
                  <template v-else>
                    <div class="flex flex-col items-center justify-center h-full">
                      <div class="p-1.5 bg-emerald-500/20 rounded-full mb-1">
                        <FileCheck class="h-5 w-5 text-emerald-600" />
                      </div>
                      <p class="text-[10px] font-black text-emerald-700 truncate w-24">{{ form.image.name }}</p>
                      <button @click="form.image = null" type="button" class="mt-2 p-1.5 bg-red-500/20 text-red-600 rounded-lg hover:bg-red-500/40 transition-colors">
                        <Trash2 class="h-3 w-3" />
                      </button>
                    </div>
                  </template>
                </div>
                <InputError class="mt-1 text-red-500" :message="form.errors.image" />
              </div>

              <!-- First Name -->
              <div>
                <InputLabel for="first_name" value="First Name" class="text-slate-700 font-semibold" />
                <TextInput id="first_name" type="text"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 placeholder:text-slate-400 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.first_name" required autofocus placeholder="Juan"
                  @keypress="blockNumbersAndSpecial($event, 'first_name')" />
                <p v-if="inputWarnings.first_name" class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse">{{ inputWarnings.first_name }}</p>
                <InputError class="mt-1 text-red-500" :message="form.errors.first_name" />
              </div>

              <!-- Middle Name -->
              <div>
                <InputLabel for="middle_name" value="Middle Name (Optional)" class="text-slate-700 font-semibold" />
                <TextInput id="middle_name" type="text"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 placeholder:text-slate-400 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.middle_name" placeholder="Santos"
                  @keypress="blockNumbersAndSpecial($event, 'middle_name')" />
                <p v-if="inputWarnings.middle_name" class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse">{{ inputWarnings.middle_name }}</p>
                <InputError class="mt-1 text-red-500" :message="form.errors.middle_name" />
              </div>

              <!-- Last Name -->
              <div>
                <InputLabel for="last_name" value="Last Name" class="text-slate-700 font-semibold" />
                <TextInput id="last_name" type="text"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 placeholder:text-slate-400 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.last_name" required placeholder="Dela Cruz"
                  @keypress="blockNumbersAndSpecial($event, 'last_name')" />
                <p v-if="inputWarnings.last_name" class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse">{{ inputWarnings.last_name }}</p>
                <InputError class="mt-1 text-red-500" :message="form.errors.last_name" />
              </div>

              <div class="md:col-span-3 mt-4 border-t border-slate-200 pt-4">
                <h3 class="text-sm font-black uppercase tracking-widest text-blue-600 pb-2">
                  Professional & Contact Details
                </h3>
              </div>

              <!-- Email -->
              <div>
                <InputLabel for="email" value="Email Address" class="text-slate-700 font-semibold" />
                <TextInput id="email" type="email"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.email" required placeholder="juan@example.com"
                  @keypress="blockSpecialForEmail($event)" />
                <p v-if="inputWarnings.email" class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse">{{ inputWarnings.email }}</p>
                <InputError class="mt-1 text-red-500" :message="form.errors.email" />
              </div>

              <!-- Phone -->
              <div>
                <InputLabel for="phone_raw" value="Phone Number" class="text-slate-700 font-semibold" />
                <div class="flex gap-2 mt-1">
                  <select v-model="form.phone_country"
                    class="w-[35%] py-3 px-1 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                    <option value="+63">+63 (PH)</option>
                    <option value="+1">+1 (US/CA)</option>
                  </select>
                  <TextInput id="phone_raw" type="tel" maxlength="12"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.phone_raw" 
                    @input="enforceNumbersOnly"
                    @keypress="blockNonNumbers"
                    required placeholder="09123456789" />
                </div>
                <p v-if="inputWarnings.phone_raw" class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse">{{ inputWarnings.phone_raw }}</p>
                <InputError class="mt-1 text-red-500" :message="form.errors.phone_raw" />
              </div>

              <!-- Position -->
              <div>
                <InputLabel for="position_applied" value="Position Applied For" class="text-slate-700 font-semibold" />
                <select 
                  id="position_applied" 
                  v-model="form.position_applied" 
                  required
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                >
                  <option value="" disabled>Select Position</option>
                  <option v-for="pos in activePositions" :key="pos.id" :value="pos.position">
                    {{ pos.position }}
                  </option>
                </select>
                <InputError class="mt-1 text-red-500" :message="form.errors.position_applied" />
              </div>

              <!-- Notice Period -->
              <div>
                <InputLabel for="notice_period" value="Notice Period" class="text-slate-700 font-semibold" />
                <select id="notice_period" v-model="form.notice_period"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                  <option value="Immediate">Immediate</option>
                  <option value="15_Days">15 Days</option>
                  <option value="30_Days">30 Days</option>
                  <option value="60_Days">60 Days</option>
                </select>
              </div>

              <!-- Address -->
              <div class="md:col-span-3">
                <InputLabel for="street_address" value="Street Address" class="text-slate-700 font-semibold" />
                <TextInput id="street_address" type="text"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.street_address" required placeholder="123 Main St, Brgy. San Jose"
                  @keypress="blockSpecialForAddress($event)" />
                <p v-if="inputWarnings.street_address" class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse">{{ inputWarnings.street_address }}</p>
                <InputError class="mt-1 text-red-500" :message="form.errors.street_address" />
              </div>
              <div>
                <InputLabel for="city" value="City/Municipality" class="text-slate-700 font-semibold" />
                <TextInput id="city" type="text"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.city" required placeholder="General Trias" />
                <InputError class="mt-1 text-red-500" :message="form.errors.city" />
              </div>
              <div>
                <InputLabel for="state_province" value="State/Province" class="text-slate-700 font-semibold" />
                <TextInput id="state_province" type="text"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.state_province" required placeholder="Cavite" />
                <InputError class="mt-1 text-red-500" :message="form.errors.state_province" />
              </div>
              <div>
                <InputLabel for="postal_zip_code" value="Postal/Zip Code" class="text-slate-700 font-semibold" />
                <TextInput id="postal_zip_code" type="text"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.postal_zip_code" required placeholder="4107" />
                <p v-if="inputWarnings.postal_zip_code" class="text-xs text-red-500 font-bold mt-1 ml-1 animate-pulse">{{ inputWarnings.postal_zip_code }}</p>
                <InputError class="mt-1 text-red-500" :message="form.errors.postal_zip_code" />
              </div>

              <div class="md:col-span-3 mt-4 border-t border-slate-200 pt-4">
                <h3 class="text-sm font-black uppercase tracking-widest text-blue-600 pb-2">
                  Other Details
                </h3>
              </div>

              <!-- Date of Birth -->
              <div>
                <InputLabel for="date_of_birth" value="Date of Birth" class="text-slate-700 font-semibold" />
                <TextInput id="date_of_birth" type="date"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.date_of_birth" />
              </div>

              <!-- Place of Birth -->
              <div>
                <InputLabel for="place_of_birth" value="Place of Birth" class="text-slate-700 font-semibold" />
                <TextInput id="place_of_birth" type="text"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.place_of_birth" placeholder="City, Province" />
              </div>

              <!-- Citizenship -->
              <div>
                <InputLabel for="citizenship" value="Citizenship" class="text-slate-700 font-semibold" />
                <TextInput id="citizenship" type="text"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.citizenship" placeholder="Filipino" />
              </div>

              <!-- Weight -->
              <div>
                <InputLabel for="weight" value="Weight (kg)" class="text-slate-700 font-semibold" />
                <TextInput id="weight" type="number" step="0.1" min="0"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.weight" placeholder="65.5" 
                  @keypress="blockNegative" />
              </div>

              <!-- Height -->
              <div>
                <InputLabel for="height" value="Height (cm)" class="text-slate-700 font-semibold" />
                <TextInput id="height" type="number" step="0.1" min="0"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.height" placeholder="170" 
                  @keypress="blockNegative" />
              </div>

              <!-- Civil Status -->
              <div>
                <InputLabel for="civil_status" value="Civil Status" class="text-slate-700 font-semibold" />
                <select id="civil_status" v-model="form.civil_status"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                  <option value="">Select</option>
                  <option value="Single">Single</option>
                  <option value="Married">Married</option>
                  <option value="Divorced">Divorced</option>
                  <option value="Widowed">Widowed</option>
                </select>
              </div>

              <!-- Sex -->
              <div>
                <InputLabel for="sex" value="Sex" class="text-slate-700 font-semibold" />
                <select id="sex" v-model="form.sex"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                  <option value="">Select</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>

              <!-- Religion -->
              <div>
                <InputLabel for="religion" value="Religion" class="text-slate-700 font-semibold" />
                <TextInput id="religion" type="text"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.religion" placeholder="Roman Catholic" />
              </div>
            </div>

            <!-- Government IDs -->
            <div class="grid grid-cols-1 gap-4">
              <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-blue-600 border-b border-slate-200 pb-2">
                  Government IDs
                </h3>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <InputLabel for="sss_number" value="SSS Number" class="text-slate-700 font-semibold" />
                  <TextInput id="sss_number" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.sss_number" placeholder="XX-XXXXXXX-X" />
                </div>
                <div>
                  <InputLabel for="philhealth_number" value="PhilHealth Number" class="text-slate-700 font-semibold" />
                  <TextInput id="philhealth_number" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.philhealth_number" placeholder="XX-XXXXXXXXX-X" />
                </div>
                <div>
                  <InputLabel for="pagibig_number" value="Pag-IBIG Number" class="text-slate-700 font-semibold" />
                  <TextInput id="pagibig_number" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.pagibig_number" placeholder="XXXX-XXXX-XXXX" />
                </div>
              </div>
            </div>

            <!-- Spouse Information -->
            <div class="grid grid-cols-1 gap-4">
              <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-blue-600 border-b border-slate-200 pb-2">
                  Spouse Information (if married)
                </h3>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <InputLabel for="spouse_name" value="Spouse's Full Name" class="text-slate-700 font-semibold" />
                  <TextInput id="spouse_name" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.spouse_name" placeholder="Juan Dela Cruz" />
                </div>
                <div>
                  <InputLabel for="spouse_occupation" value="Spouse's Occupation" class="text-slate-700 font-semibold" />
                  <TextInput id="spouse_occupation" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.spouse_occupation" placeholder="Engineer" />
                </div>
                <div class="md:col-span-2">
                  <InputLabel for="spouse_address" value="Spouse's Address" class="text-slate-700 font-semibold" />
                  <TextInput id="spouse_address" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.spouse_address" placeholder="Complete address" />
                </div>
              </div>
            </div>

            <!-- Children -->
            <div class="grid grid-cols-1 gap-4">
              <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-blue-600 border-b border-slate-200 pb-2">
                  Children
                </h3>
              </div>
              <div>
                <button type="button" @click="addChild"
                  class="inline-flex items-center gap-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-all">
                  <Plus class="w-4 h-4" /> Add Child
                </button>
              </div>
              <div v-for="(child, idx) in children" :key="idx"
                class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-slate-50 rounded-xl relative">
                <button type="button" @click="removeChild(idx)"
                  class="absolute top-2 right-2 p-1 text-red-400 hover:text-red-600">
                  <X class="h-4 w-4" />
                </button>
                <div>
                  <InputLabel :for="`child_name_${idx}`" value="Name" class="text-slate-600 text-xs" />
                  <TextInput :id="`child_name_${idx}`" type="text"
                    class="mt-1 block w-full py-2 px-3 bg-white border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="child.name" placeholder="Full name" />
                </div>
                <div>
                  <InputLabel :for="`child_dob_${idx}`" value="Date of Birth" class="text-slate-600 text-xs" />
                  <TextInput :id="`child_dob_${idx}`" type="date"
                    class="mt-1 block w-full py-2 px-3 bg-white border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="child.dob" />
                </div>
              </div>
            </div>

            <!-- Parents Information -->
            <div class="grid grid-cols-1 gap-4">
              <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-blue-600 border-b border-slate-200 pb-2">
                  Parents Information
                </h3>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <InputLabel for="mother_name" value="Mother's Name" class="text-slate-700 font-semibold" />
                  <TextInput id="mother_name" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.mother_name" placeholder="Full name" />
                </div>
                <div>
                  <InputLabel for="mother_address" value="Mother's Address" class="text-slate-700 font-semibold" />
                  <TextInput id="mother_address" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.mother_address" placeholder="Address" />
                </div>
                <div>
                  <InputLabel for="father_name" value="Father's Name" class="text-slate-700 font-semibold" />
                  <TextInput id="father_name" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.father_name" placeholder="Full name" />
                </div>
                <div>
                  <InputLabel for="father_address" value="Father's Address" class="text-slate-700 font-semibold" />
                  <TextInput id="father_address" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.father_address" placeholder="Address" />
                </div>
              </div>
            </div>

            <!-- Languages -->
            <div>
              <InputLabel for="languages" value="Language(s) You Can Speak or Write" class="text-slate-700 font-semibold" />
              <TextInput id="languages" type="text"
                class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                v-model="form.languages" placeholder="Tagalog, English, etc." />
            </div>

            <!-- Emergency Contact -->
            <div class="grid grid-cols-1 gap-4">
              <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-blue-600 border-b border-slate-200 pb-2">
                  Emergency Contact
                </h3>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <InputLabel for="emergency_name" value="Name" class="text-slate-700 font-semibold" />
                  <TextInput id="emergency_name" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.emergency_name" placeholder="Full name" />
                </div>
                <div>
                  <InputLabel for="emergency_relationship" value="Relationship" class="text-slate-700 font-semibold" />
                  <TextInput id="emergency_relationship" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.emergency_relationship" placeholder="Spouse, Parent, etc." />
                </div>
                <div>
                  <InputLabel for="emergency_phone" value="Telephone Number" class="text-slate-700 font-semibold" />
                  <TextInput id="emergency_phone" type="tel"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.emergency_phone" placeholder="09123456789" />
                </div>
                <div class="md:col-span-2">
                  <InputLabel for="emergency_address" value="Address" class="text-slate-700 font-semibold" />
                  <TextInput id="emergency_address" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.emergency_address" placeholder="Complete address" />
                </div>
              </div>
            </div>

            <!-- Educational Background -->
            <div class="grid grid-cols-1 gap-4">
              <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-blue-600 border-b border-slate-200 pb-2">
                  Educational Background
                </h3>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <InputLabel for="elementary_school" value="Elementary School" class="text-slate-700 font-semibold" />
                  <TextInput id="elementary_school" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.elementary_school" placeholder="School name" />
                </div>
                <div>
                  <InputLabel for="elementary_year" value="Year Graduated" class="text-slate-700 font-semibold" />
                  <TextInput id="elementary_year" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.elementary_year" placeholder="YYYY" />
                </div>
                <div>
                  <InputLabel for="high_school" value="High School" class="text-slate-700 font-semibold" />
                  <TextInput id="high_school" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.high_school" placeholder="School name" />
                </div>
                <div>
                  <InputLabel for="high_year" value="Year Graduated" class="text-slate-700 font-semibold" />
                  <TextInput id="high_year" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.high_year" placeholder="YYYY" />
                </div>
                <div>
                  <InputLabel for="college" value="College" class="text-slate-700 font-semibold" />
                  <TextInput id="college" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.college" placeholder="Course & School" />
                </div>
                <div>
                  <InputLabel for="college_year" value="Year Graduated" class="text-slate-700 font-semibold" />
                  <TextInput id="college_year" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.college_year" placeholder="YYYY" />
                </div>
                <div>
                  <InputLabel for="vocational" value="Vocational" class="text-slate-700 font-semibold" />
                  <TextInput id="vocational" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.vocational" placeholder="Course & School" />
                </div>
                <div>
                  <InputLabel for="vocational_year" value="Year Graduated" class="text-slate-700 font-semibold" />
                  <TextInput id="vocational_year" type="text"
                    class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    v-model="form.vocational_year" placeholder="YYYY" />
                </div>
              </div>
              <div>
                <InputLabel for="special_skills" value="Special Skills" class="text-slate-700 font-semibold" />
                <TextInput id="special_skills" type="text"
                  class="mt-1 block w-full py-3 px-4 bg-slate-50 border border-slate-300 text-slate-900 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  v-model="form.special_skills" placeholder="e.g., Microsoft Office, Sewing" />
              </div>
            </div>

            <!-- Submit -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-8 border-t border-slate-200 mt-6">
              <div class="flex items-center text-[10px] font-black uppercase tracking-widest text-slate-500">
                <ShieldCheck class="h-5 w-5 text-blue-500 mr-2" /> Data Encryption Active
              </div>
              <PrimaryButton
                class="w-full sm:w-auto px-10 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl shadow-lg shadow-blue-700/30 transition-all duration-200"
                :class="{ 'opacity-60 cursor-wait': form.processing }" :disabled="form.processing">
                <span v-if="form.processing">Processing...</span>
                <span v-else class="flex items-center gap-2">
                  <Save class="w-4 h-4" /> Submit Application
                </span>
              </PrimaryButton>
            </div>
          </form>
        </div>
      </div>

      <!-- SUCCESS MODAL -->
      <Teleport to="body">
        <Transition name="modal">
          <div v-if="showSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-md">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-8 border border-slate-200/60 text-center">
              <div class="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4">
                <CheckCircle2 class="w-10 h-10 text-emerald-500" />
              </div>
              <h3 class="text-xl font-bold text-slate-900 mb-2">Application Received</h3>
              <p class="text-sm text-slate-500 mb-6">Your application has been submitted successfully.</p>
              <Link :href="route('hrm.applications.index')"
                class="inline-flex items-center justify-center w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl transition-all shadow-lg shadow-blue-500/20">
                Return to Forms
              </Link>
            </div>
          </div>
        </Transition>
      </Teleport>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    FileCheck, Upload, Trash2, ShieldCheck, Save, CheckCircle2, Plus, X
} from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const isLoaded = ref(false);
const showSuccessModal = ref(false); 

const children = ref([]);
const employmentRecords = ref([]);
const showEmploymentSection = ref(false);

const addChild = () => {
    children.value.push({ name: '', dob: '' });
};
const removeChild = (index) => { 
    children.value.splice(index, 1);
};

const addEmployment = () => {
    employmentRecords.value.push({ company: '', years: '', salary: '', position: '', reason: '' });
};
const removeEmployment = (index) => {
    employmentRecords.value.splice(index, 1);
};

const form = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    phone_country: '+63',
    phone_raw: '',
    phone_number: '',
    street_address: '',
    city: '',
    state_province: '',
    postal_zip_code: '',
    position_applied: '',
    notice_period: 'Immediate',
    sss_file: null,
    philhealth_file: null,
    pagibig_file: null,
    status: 'pending',
    image: null,
    date_of_birth: '',
    place_of_birth: '',
    citizenship: '',
    weight: '',
    height: '',
    civil_status: '',
    sex: '',
    religion: '',
    contact_number: '',
    sss_number: '',
    philhealth_number: '',
    pagibig_number: '',
    spouse_name: '',
    spouse_occupation: '',
    spouse_address: '',
    number_of_children: 0,
    mother_name: '',
    mother_address: '',
    father_name: '',
    father_address: '',
    languages: '',
    children: [],
    employment_records: [],
    emergency_name: '',
    emergency_relationship: '',
    emergency_phone: '',
    emergency_address: '',
    elementary_school: '',
    elementary_year: '',
    high_school: '',
    high_year: '',
    college: '',
    college_year: '',
    vocational: '',
    vocational_year: '',
    special_skills: '',
    has_employment_record: false,
    machine_operation: '',
    referred_by: '',
    referred_by_address: '',
    previous_employment_company: '',
    previous_employment_when: '',
    previous_employment_position: '',
    previous_employment_department: '',
    related_employees: '',
});

watch(showEmploymentSection, (val) => {
    form.has_employment_record = val;
});

const sanitizeWithFallback = (original, pattern, field) => {
    const filtered = original.replace(pattern, '');
    if (filtered === '' && original !== '') {
        triggerWarning(field, 'Invalid characters detected – please use only allowed characters.');
        return original;
    }
    return filtered;
};

const submitForm = () => {
    if (!/^[a-zA-Z\sñÑ-]+$/.test(form.first_name) || !/^[a-zA-Z\sñÑ-]+$/.test(form.last_name)) {
        toast.error('First Name and Last Name must only contain letters.');
        return;
    }

    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!emailRegex.test(form.email)) {
        toast.error('Please enter a valid email address.');
        return;
    }

    if (form.phone_raw.length < 10 || !/^\d{10,12}$/.test(form.phone_raw)) {
        toast.error('Phone number must be valid (10-12 digits). e.g. 09123456789');
        return;
    }

    if (!form.position_applied) {
        toast.error('Please select the position you are applying for.');
        return;
    }

    if (form.weight !== '' && form.weight !== null && parseFloat(form.weight) <= 0) {
        toast.error('Weight must be greater than 0.');
        return;
    }
    if (form.height !== '' && form.height !== null && parseFloat(form.height) <= 0) {
        toast.error('Height must be greater than 0.');
        return;
    }

    const street = form.street_address?.trim() || '';
    const city = form.city?.trim() || '';
    const state = form.state_province?.trim() || '';
    if (!street || !city || !state) {
        let missing = [];
        if (!street) missing.push('Street Address');
        if (!city) missing.push('City');
        if (!state) missing.push('State/Province');
        toast.error(`Complete residential details are required. Missing: ${missing.join(', ')}`);
        return;
    }

    if (form.postal_zip_code.length !== 4) {
        toast.error('Postal/Zip code must be exactly 4 digits.');
        return;
    }

    form.phone_number = `${form.phone_country}${form.phone_raw}`;
    form.children = children.value;
    form.employment_records = employmentRecords.value;
    form.number_of_children = children.value.length;

    if (form.weight) form.weight = parseFloat(form.weight);
    if (form.height) form.height = parseFloat(form.height);

    // ✅ UPDATED: Use the new route for form submission
    form.post(route('hrm.temp.application.submit'), {
        forceFormData: true,
        onSuccess: () => {
            showSuccessModal.value = true;
            setTimeout(() => {
                router.visit(route('hrm.temp.application.index'));
            }, 3000);
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Application submission failed. Please check your inputs.';
            toast.error(errorMsg);
        }
    });
};

const handleImageUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.image = file;
    }
};

const handleFileUpload = (e, type) => {
    const file = e.target.files[0];
    if (file) {
        form[type + '_file'] = file;
    }
};

const removeFile = (type) => {
    form[type + '_file'] = null;
};

const inputWarnings = ref({});
let warningTimeouts = {};

const triggerWarning = (field, message) => {
    inputWarnings.value[field] = message;
    if (warningTimeouts[field]) clearTimeout(warningTimeouts[field]);
    warningTimeouts[field] = setTimeout(() => {
        inputWarnings.value[field] = '';
    }, 3000);
};

const blockNumbersAndSpecial = (e, field) => {
    if (e.key.length === 1 && !/^[a-zA-Z\sñÑ-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning(field, 'Numbers and special characters are not allowed.');
    }
};

const blockNonNumeric = (e, field) => {
    if (e.key.length === 1 && !/^\d$/.test(e.key)) {
        e.preventDefault();
        triggerWarning(field, 'Letters and special characters are not allowed.');
    }
};

const blockSpecialForAddress = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9\sñÑ.,#-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning('street_address', 'Invalid character. Use alphanumeric and basic punctuation.');
    }
};

const blockSpecialForEmail = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9@.\-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning('email', 'Invalid character. Only letters, numbers, @, ., and - are allowed.');
    }
};

const blockNegative = (e) => {
    if (e.key === '-' || e.key === '+' || e.key === 'e' || e.key === 'E') {
        e.preventDefault();
    }
};

const sanitizeName = (val, field) => {
    const filtered = sanitizeWithFallback(val, /[^a-zA-Z\sñÑ-]/g, field);
    if (val !== filtered) {
        form[field] = filtered;
        triggerWarning(field, 'Invalid characters removed.');
    }
};

watch(() => form.first_name, (val) => sanitizeName(val, 'first_name'));
watch(() => form.middle_name, (val) => sanitizeName(val, 'middle_name'));
watch(() => form.last_name, (val) => sanitizeName(val, 'last_name'));
watch(() => form.city, (val) => sanitizeName(val, 'city'));
watch(() => form.state_province, (val) => sanitizeName(val, 'state_province'));

watch(() => form.street_address, (val) => {
    const filtered = sanitizeWithFallback(val, /[^a-zA-Z0-9\sñÑ.,#-]/g, 'street_address');
    if (val !== filtered) form.street_address = filtered;
});

watch(() => form.email, (val) => {
    const filtered = val.replace(/[^a-zA-Z0-9@.\-]/g, '');
    if (val !== filtered) {
        form.email = filtered;
        triggerWarning('email', 'Invalid characters removed.');
    }
});

watch(() => form.phone_raw, (val) => {
    const filtered = val.replace(/\D/g, '').substring(0, 12);
    if (val !== filtered) {
        form.phone_raw = filtered;
        triggerWarning('phone_raw', 'Numbers only.');
    }
});

watch(() => form.postal_zip_code, (val) => {
    const filtered = val.replace(/\D/g, '').substring(0, 4);
    if (val !== filtered) {
        form.postal_zip_code = filtered;
        triggerWarning('postal_zip_code', 'Only digits allowed.');
    }
});

const enforceNumbersOnly = (event) => {
    const cleanedValue = event.target.value.replace(/[^0-9]/g, '');
    event.target.value = cleanedValue;
    form.phone_raw = cleanedValue;
};

const blockNonNumbers = (event) => {
    if (!/[0-9]/.test(event.key)) {
        event.preventDefault();
    }
};

const activePositions = ref([]);

const fetchActivePositions = async () => {
    try {
        // ✅ UPDATED: Use the route helper
        const response = await fetch(route('active.positions'));
        if (response.ok) {
            const data = await response.json();
            activePositions.value = data;
        }
    } catch (error) {
        console.error("Error fetching active positions:", error);
    }
};

onMounted(() => {
    isLoaded.value = true;
    fetchActivePositions();
});
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap');

.font-mono {
    font-family: 'JetBrains Mono', monospace;
}

input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus,
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 30px rgba(255, 255, 255, 0.08) inset !important;
    -webkit-text-fill-color: white !important;
}

input,
select,
textarea {
    @apply transition-all duration-300 ease-in-out;
}

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

/* Background Grid Pattern */
.bg-grid-slate-100 {
  background-image: radial-gradient(circle, #e2e8f0 1px, transparent 1px);
  background-size: 24px 24px;
}

/* Modal Transitions */
.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.modal-enter-from {
  opacity: 0;
  transform: scale(0.95);
}
.modal-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
</style>