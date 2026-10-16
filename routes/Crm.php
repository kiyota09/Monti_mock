<?php

use App\Http\Controllers\Crm\ActivityController;
use App\Http\Controllers\Crm\ApprovalController;
use App\Http\Controllers\Crm\CampaignController;
use App\Http\Controllers\Crm\CaseController;
use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\CrmDashboardController;
use App\Http\Controllers\Crm\CrmInquiryController;
use App\Http\Controllers\Crm\CustomerProfileController;

use App\Http\Controllers\Crm\InvestigationController;
use App\Http\Controllers\Crm\LeadController;
use App\Http\Controllers\Crm\OpportunityController;
use App\Http\Controllers\Crm\QuotationController;
use App\Http\Controllers\Crm\StageController;
use App\Http\Controllers\Crm\CrmLogoPartnerController;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Relationship Management (CRM) Routes
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/crm')->name('crm.')->middleware(['auth', 'verified', 'module.access:CRM'])->group(function () {
    // Dashboard
    Route::get('/', [CrmDashboardController::class, 'index'])
        ->middleware('page.permission:dashboard,view')
        ->name('dashboard');

    // Leads
    Route::get('/lead', [LeadController::class, 'index'])
        ->middleware('page.permission:leads,view')
        ->name('lead');
    Route::post('/lead/store', [LeadController::class, 'store'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.store');
    Route::patch('/lead/{id}/status', [LeadController::class, 'updateStatus'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.status');
    Route::post('/lead/convert', [LeadController::class, 'convertToClient'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.convert');
    Route::post('/lead/{id}/note', [LeadController::class, 'addNote'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.add-note');
    Route::post('/lead/{id}/interview', [LeadController::class, 'scheduleInterview'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.schedule-interview');
    Route::post('/lead/{id}/file', [LeadController::class, 'uploadApprovalFile'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.upload-file');
    Route::post('/lead/{id}/accept', [LeadController::class, 'acceptLead'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.accept');
    Route::post('/lead/{id}/reject', [LeadController::class, 'rejectLead'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.reject');
    Route::post('/lead/{id}/qualify', [LeadController::class, 'qualify'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.qualify');
    Route::post('/lead/{id}/pipeline', [LeadController::class, 'sendToPipeline'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.pipeline');



    // Approvals
    Route::get('/approval', [ApprovalController::class, 'index'])
        ->middleware('page.permission:approvals,view')
        ->name('approval.index');
    Route::post('/approval/{id}/note', [ApprovalController::class, 'addNote'])
        ->middleware('page.permission:approvals,edit')
        ->name('approval.note');
    Route::post('/approval/{id}/meeting', [ApprovalController::class, 'scheduleMeeting'])
        ->middleware('page.permission:approvals,edit')
        ->name('approval.meeting');
    Route::patch('/approval/meeting/{meetingId}/status', [ApprovalController::class, 'updateMeetingStatus'])
        ->middleware('page.permission:approvals,edit')
        ->name('approval.meeting.update');
    Route::post('/approval/{id}/accept', [ApprovalController::class, 'accept'])
        ->middleware('page.permission:approvals,edit')
        ->name('approval.accept');
    Route::post('/approval/{id}/reject', [ApprovalController::class, 'reject'])
        ->middleware('page.permission:approvals,edit')
        ->name('approval.reject');

    // Customer Profiles = Accounts (company 360)
    Route::get('/customerprofile', [CustomerProfileController::class, 'index'])
        ->middleware('page.permission:customer_profiles,view')
        ->name('customerprofile.index');
    Route::get('/customerprofile/{id}', [CustomerProfileController::class, 'show'])
        ->middleware('page.permission:customer_profiles,view')
        ->name('customerprofile.show');

    // Contacts (people at accounts / prospects)
    Route::post('/contacts', [ContactController::class, 'store'])
        ->middleware('page.permission:customer_profiles,edit')
        ->name('contacts.store');
    Route::patch('/contacts/{contact}', [ContactController::class, 'update'])
        ->middleware('page.permission:customer_profiles,edit')
        ->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])
        ->middleware('page.permission:customer_profiles,edit')
        ->name('contacts.destroy');
    Route::post('/contacts/{contact}/primary', [ContactController::class, 'makePrimary'])
        ->middleware('page.permission:customer_profiles,edit')
        ->name('contacts.primary');

    // Client inquiries & conversations — moved here from ECO: highly customized
    // orders go through multiple discussion/agreement rounds, so they belong
    // with the relationship workflow (leads → inquiries → quotations).
    Route::get('/inquiries', [CrmInquiryController::class, 'index'])
        ->middleware('page.permission:inquiry,view')
        ->name('inquiries');
    Route::get('/inquiries/{inquiry}', [CrmInquiryController::class, 'show'])
        ->middleware('page.permission:inquiry,view')
        ->name('inquiry.show');
    Route::post('/inquiries/{inquiry}/message', [CrmInquiryController::class, 'sendMessage'])
        ->middleware('page.permission:inquiry,edit')
        ->name('inquiry.message');
    // Real-time: polling feed + typing heartbeat (no page reload needed)
    Route::get('/inquiries/{inquiry}/feed', [CrmInquiryController::class, 'feed'])
        ->middleware('page.permission:inquiry,view')
        ->name('inquiry.feed');
    Route::post('/inquiries/{inquiry}/typing', [CrmInquiryController::class, 'typing'])
        ->middleware('page.permission:inquiry,edit')
        ->name('inquiry.typing');
    Route::post('/inquiries/{inquiry}/meeting', [CrmInquiryController::class, 'setMeeting'])
        ->middleware('page.permission:inquiry,edit')
        ->name('inquiry.meeting');
    Route::post('/inquiries/{inquiry}/quotation', [CrmInquiryController::class, 'issueQuotation'])
        ->middleware('page.permission:inquiry,edit')
        ->name('inquiry.quotation');
    Route::post('/inquiries/{inquiry}/reject', [CrmInquiryController::class, 'reject'])
        ->middleware('page.permission:inquiry,edit')
        ->name('inquiry.reject');
    // Fabric sample loop: request from the lab, then forward the result.
    Route::post('/inquiries/{inquiry}/sample-request', [CrmInquiryController::class, 'requestSample'])
        ->middleware('page.permission:inquiry,edit')
        ->name('inquiry.sample-request');
    // Attachment actions (previously eco.attachment.*)
    Route::post('/attachment/{attachment}/create-recipe', [CrmInquiryController::class, 'createRecipeFromAttachment'])
        ->middleware('page.permission:inquiry,edit')
        ->name('attachment.create-recipe');
    Route::post('/attachment/{attachment}/create-job-order', [CrmInquiryController::class, 'createJobOrderFromPO'])
        ->middleware('page.permission:inquiry,edit')
        ->name('attachment.create-job-order');

    // Opportunities — Odoo-style pipeline board + detail form
    Route::get('/opportunities', [OpportunityController::class, 'index'])
        ->middleware('page.permission:opportunities,view')
        ->name('opportunities');
    Route::post('/opportunities', [OpportunityController::class, 'store'])
        ->middleware('page.permission:opportunities,edit')
        ->name('opportunities.store');
    Route::get('/opportunities/{opportunity}', [OpportunityController::class, 'show'])
        ->middleware('page.permission:opportunities,view')
        ->name('opportunities.show');
    Route::patch('/opportunities/{opportunity}', [OpportunityController::class, 'update'])
        ->middleware('page.permission:opportunities,edit')
        ->name('opportunities.update');
    Route::post('/opportunities/{opportunity}/move', [OpportunityController::class, 'move'])
        ->middleware('page.permission:opportunities,edit')
        ->name('opportunities.move');
    Route::post('/opportunities/{opportunity}/priority', [OpportunityController::class, 'setPriority'])
        ->middleware('page.permission:opportunities,edit')
        ->name('opportunities.priority');

    // Pipeline stages (custom columns)
    Route::post('/stages', [StageController::class, 'store'])
        ->middleware('page.permission:opportunities,edit')
        ->name('stages.store');
    Route::patch('/stages/{stage}', [StageController::class, 'update'])
        ->middleware('page.permission:opportunities,edit')
        ->name('stages.update');
    Route::post('/stages/reorder', [StageController::class, 'reorder'])
        ->middleware('page.permission:opportunities,edit')
        ->name('stages.reorder');
    Route::delete('/stages/{stage}', [StageController::class, 'destroy'])
        ->middleware('page.permission:opportunities,edit')
        ->name('stages.destroy');

    // Activities (unified timeline)
    Route::get('/activities', [ActivityController::class, 'index'])
        ->middleware('page.permission:activities,view')
        ->name('activities');
    Route::post('/activities', [ActivityController::class, 'store'])
        ->middleware('page.permission:activities,edit')
        ->name('activities.store');
    Route::post('/activities/{activity}/done', [ActivityController::class, 'done'])
        ->middleware('page.permission:activities,edit')
        ->name('activities.done');
    Route::post('/activities/{activity}/cancel', [ActivityController::class, 'cancel'])
        ->middleware('page.permission:activities,edit')
        ->name('activities.cancel');
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])
        ->middleware('page.permission:activities,edit')
        ->name('activities.destroy');

    // Cases (complaints & claims)
    Route::get('/cases', [CaseController::class, 'index'])
        ->middleware('page.permission:cases,view')
        ->name('cases');
    Route::post('/cases', [CaseController::class, 'store'])
        ->middleware('page.permission:cases,edit')
        ->name('cases.store');
    Route::post('/cases/{case}/status', [CaseController::class, 'status'])
        ->middleware('page.permission:cases,edit')
        ->name('cases.status');

    // Campaigns (with lead attribution)
    Route::get('/campaigns', [CampaignController::class, 'index'])
        ->middleware('page.permission:campaigns,view')
        ->name('campaigns');
    Route::post('/campaigns', [CampaignController::class, 'store'])
        ->middleware('page.permission:campaigns,edit')
        ->name('campaigns.store');
    Route::post('/campaigns/{campaign}/leads', [CampaignController::class, 'attachLead'])
        ->middleware('page.permission:campaigns,edit')
        ->name('campaigns.attach-lead');
    Route::post('/campaigns/{campaign}/status', [CampaignController::class, 'status'])
        ->middleware('page.permission:campaigns,edit')
        ->name('campaigns.status');

    // Quotations (ECO-issued, read-only in CRM)
    Route::get('/quotations', [QuotationController::class, 'index'])
        ->middleware('page.permission:quotations,view')
        ->name('quotations');

    // Investigation
    Route::get('/investigation', [InvestigationController::class, 'index'])
        ->middleware('page.permission:investigation,view')
        ->name('investigation.index');
    Route::post('/investigation/assign', [InvestigationController::class, 'assignStaff'])
        ->middleware('page.permission:investigation,edit')
        ->name('investigation.assign');
    Route::post('/investigation/feedback', [InvestigationController::class, 'storeFeedback'])
        ->middleware('page.permission:investigation,edit')
        ->name('investigation.feedback.store');
    Route::patch('/investigation/feedback/{id}/status', [InvestigationController::class, 'updateFeedbackStatus'])
        ->middleware('page.permission:investigation,edit')
        ->name('investigation.feedback.status');

    // Partner logos (company logos uploaded at registration / lead creation)
    Route::post('/logo-partner/upload', [CrmLogoPartnerController::class, 'upload'])
        ->middleware('page.permission:leads,edit')
        ->name('logo-partner.upload');
    Route::delete('/logo-partner/{id}', [CrmLogoPartnerController::class, 'destroy'])
        ->middleware('page.permission:leads,edit')
        ->name('logo-partner.destroy');

});