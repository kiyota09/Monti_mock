<?php

use App\Http\Controllers\Crm\ActivityController;
use App\Http\Controllers\Crm\ApprovalController;
use App\Http\Controllers\Crm\CallController;
use App\Http\Controllers\Crm\CampaignController;
use App\Http\Controllers\Crm\CaseController;
use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\CrmDashboardController;
use App\Http\Controllers\Crm\CrmEmailController;
use App\Http\Controllers\Crm\CrmInquiryController;
use App\Http\Controllers\Crm\CrmLogoPartnerController;
use App\Http\Controllers\Crm\CrmMessageTemplateController;
use App\Http\Controllers\Crm\CrmPostController;
use App\Http\Controllers\Crm\CrmRegistrationController;
use App\Http\Controllers\Crm\CustomerProfileController;
use App\Http\Controllers\Crm\InvestigationController;
use App\Http\Controllers\Crm\LeadController;
use App\Http\Controllers\Crm\LeadInvestigationController;
use App\Http\Controllers\Crm\MeetingRequestController;
use App\Http\Controllers\Crm\MessageController;
use App\Http\Controllers\Crm\OpportunityController;
use App\Http\Controllers\Crm\OpportunityDocumentController;
use App\Http\Controllers\Crm\PipelineNoteController;
use App\Http\Controllers\Crm\QuotationController;
use App\Http\Controllers\Crm\StageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Relationship Management (CRM) Routes
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/crm')->name('crm.')->middleware(['auth', 'verified', 'module.access:CRM'])->group(function () {
    Route::get('/posts', [CrmPostController::class, 'index'])
        ->middleware('page.permission:campaigns,view')->name('posts');
    Route::post('/posts', [CrmPostController::class, 'store'])
        ->middleware('page.permission:campaigns,edit')->name('posts.store');
    Route::post('/posts/{post}', [CrmPostController::class, 'update'])
        ->middleware('page.permission:campaigns,edit')->name('posts.update');
    Route::post('/posts/{post}/archive', [CrmPostController::class, 'archive'])
        ->middleware('page.permission:campaigns,edit')->name('posts.archive');
    Route::post('/posts/{post}/restore', [CrmPostController::class, 'restore'])
        ->middleware('page.permission:campaigns,edit')->name('posts.restore');
    Route::delete('/posts/{post}', [CrmPostController::class, 'destroy'])
        ->middleware('page.permission:campaigns,edit')->name('posts.destroy');

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

    // Lead investigation log (notes / flags / files before the pipeline)
    Route::post('/lead/{lead}/investigations', [LeadInvestigationController::class, 'store'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.investigations.store');
    Route::post('/lead/{lead}/investigations/files', [LeadInvestigationController::class, 'storeFiles'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.investigations.files');
    Route::delete('/lead/investigations/{investigation}', [LeadInvestigationController::class, 'destroy'])
        ->middleware('page.permission:leads,edit')
        ->name('lead.investigations.destroy');

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
    // Request a call/meeting for a pending client (same flow as pipeline cards).
    Route::post('/approval/{id}/activity', [ApprovalController::class, 'storeActivity'])
        ->middleware('page.permission:approvals,edit')
        ->name('approval.activity');
    // Files/images uploaded directly on a pending client.
    Route::post('/approval/{id}/documents', [ApprovalController::class, 'storeDocument'])
        ->middleware('page.permission:approvals,edit')
        ->name('approval.documents.store');
    Route::delete('/approval/documents/{document}', [ApprovalController::class, 'destroyDocument'])
        ->middleware('page.permission:approvals,edit')
        ->name('approval.documents.destroy');

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
    Route::post('/contacts/{contact}/primary', [ContactController::class, 'makePrimary'])
        ->middleware('page.permission:customer_profiles,edit')
        ->name('contacts.primary');
    Route::post('/contacts/{contact}/create-account', [ContactController::class, 'createAccount'])
        ->middleware('page.permission:customer_profiles,edit')
        ->name('contacts.create-account');
    Route::post('/contacts/{contact}/reject-account', [ContactController::class, 'rejectAccount'])
        ->middleware('page.permission:customer_profiles,edit')
        ->name('contacts.reject-account');

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
    Route::post('/opportunities/{opportunity}/convert', [OpportunityController::class, 'convertToClient'])
        ->middleware('page.permission:opportunities,edit')
        ->name('opportunities.convert');
    Route::post('/opportunities/{opportunity}/archive', [OpportunityController::class, 'archive'])
        ->middleware('page.permission:opportunities,edit')
        ->name('opportunities.archive');

    // Card files/images (shown on stages with allows_uploads enabled).
    Route::post('/opportunities/{opportunity}/documents', [OpportunityDocumentController::class, 'store'])
        ->middleware('page.permission:opportunities,edit')
        ->name('opportunities.documents.store');
    Route::delete('/opportunities/documents/{document}', [OpportunityDocumentController::class, 'destroy'])
        ->middleware('page.permission:opportunities,edit')
        ->name('opportunities.documents.destroy');

    // Shared progress log for the Opportunities board (floating Notes panel).
    Route::post('/opportunities/notes', [PipelineNoteController::class, 'store'])
        ->middleware('page.permission:opportunities,edit')
        ->name('opportunities.notes.store');
    Route::delete('/opportunities/notes/{note}', [PipelineNoteController::class, 'destroy'])
        ->middleware('page.permission:opportunities,edit')
        ->name('opportunities.notes.destroy');

    // Pipeline stages (custom columns). Column order is derived from each
    // stage's default probability — stages are no longer dragged.
    Route::post('/stages', [StageController::class, 'store'])
        ->middleware('page.permission:opportunities,edit')
        ->name('stages.store');
    Route::patch('/stages/{stage}', [StageController::class, 'update'])
        ->middleware('page.permission:opportunities,edit')
        ->name('stages.update');
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
    Route::patch('/activities/{activity}', [ActivityController::class, 'update'])
        ->middleware('page.permission:activities,edit')
        ->name('activities.update');
    Route::post('/activities/{activity}/done', [ActivityController::class, 'done'])
        ->middleware('page.permission:activities,edit')
        ->name('activities.done');
    Route::post('/activities/{activity}/undone', [ActivityController::class, 'undone'])
        ->middleware('page.permission:activities,edit')
        ->name('activities.undone');
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

    // Direct messaging with business partners (flat thread per client company)
    Route::get('/messages', [MessageController::class, 'index'])
        ->middleware('page.permission:messaging,view')
        ->name('messages');
    Route::patch('/messages/call-settings', [MessageController::class, 'updateCallSettings'])
        ->middleware('page.permission:messaging,edit')
        ->name('messages.call-settings');
    Route::get('/messages/incoming-calls', [MessageController::class, 'incomingCalls'])
        ->middleware('page.permission:messaging,view')
        ->name('messages.incoming-calls');
    Route::get('/messages/{client}/feed', [MessageController::class, 'feed'])
        ->middleware('page.permission:messaging,view')
        ->name('messages.feed');
    Route::post('/messages/{client}', [MessageController::class, 'store'])
        ->middleware('page.permission:messaging,edit')
        ->name('messages.store');
    Route::post('/messages/{client}/{message}/unsend-for-me', [MessageController::class, 'unsendForMe'])
        ->middleware('page.permission:messaging,edit')
        ->name('messages.unsend-for-me');
    Route::post('/messages/{client}/{message}/unsend-for-everyone', [MessageController::class, 'unsendForEveryone'])
        ->middleware('page.permission:messaging,edit')
        ->name('messages.unsend-for-everyone');
    Route::post('/messages/{client}/{message}/react', [MessageController::class, 'toggleReaction'])
        ->middleware('page.permission:messaging,edit')
        ->name('messages.react');
    Route::post('/messages/{client}/calls/start', [MessageController::class, 'startCall'])
        ->middleware('page.permission:messaging,edit')
        ->name('messages.calls.start');
    Route::post('/messages/{client}/calls/{message}/end', [MessageController::class, 'endCall'])
        ->middleware('page.permission:messaging,edit')
        ->name('messages.calls.end');
    Route::post('/messages/{client}/calls/{message}/answer', [MessageController::class, 'answerCall'])
        ->middleware('page.permission:messaging,edit')
        ->name('messages.calls.answer');
    Route::post('/messages/{client}/calls/{message}/signal', [MessageController::class, 'signalCall'])
        ->middleware('page.permission:messaging,edit')
        ->name('messages.calls.signal');
    Route::get('/messages/{client}/calls/{message}/signals', [MessageController::class, 'callSignals'])
        ->middleware('page.permission:messaging,view')
        ->name('messages.calls.signals');

    // Client email inbox — received client email, replies and registration
    // form invitations.
    Route::get('/email', [CrmEmailController::class, 'index'])
        ->middleware('page.permission:email,view')
        ->name('email.index');
    Route::post('/email/compose', [CrmEmailController::class, 'compose'])
        ->middleware('page.permission:email,edit')
        ->name('email.compose');
    Route::post('/email/sync', [CrmEmailController::class, 'sync'])
        ->middleware('page.permission:email,view')
        ->name('email.sync');
    Route::post('/email/{thread}/reply', [CrmEmailController::class, 'reply'])
        ->middleware('page.permission:email,edit')
        ->name('email.reply');
    Route::post('/email/{thread}/registration-form', [CrmEmailController::class, 'sendRegistrationForm'])
        ->middleware('page.permission:email,edit')
        ->name('email.registration-form');

    // Floating message-template widget (per-user, per-page storage).
    Route::get('/templates', [CrmMessageTemplateController::class, 'index'])
        ->name('templates.index');
    Route::post('/templates', [CrmMessageTemplateController::class, 'store'])
        ->name('templates.store');
    Route::patch('/templates/{template}', [CrmMessageTemplateController::class, 'update'])
        ->name('templates.update');
    Route::delete('/templates/{template}', [CrmMessageTemplateController::class, 'destroy'])
        ->name('templates.destroy');

    // Internal CRM user-to-user calls (voice / video).
    Route::get('/calls/contacts', [CallController::class, 'contacts'])
        ->middleware('page.permission:messaging,view')
        ->name('calls.contacts');
    Route::get('/calls/incoming', [CallController::class, 'incoming'])
        ->middleware('page.permission:messaging,view')
        ->name('calls.incoming');
    Route::get('/calls/history', [CallController::class, 'history'])
        ->middleware('page.permission:messaging,view')
        ->name('calls.history');
    Route::post('/calls', [CallController::class, 'store'])
        ->middleware('page.permission:messaging,edit')
        ->name('calls.store');
    Route::get('/calls/{call}', [CallController::class, 'show'])
        ->middleware('page.permission:messaging,view')
        ->name('calls.show');
    Route::post('/calls/{call}/answer', [CallController::class, 'answer'])
        ->middleware('page.permission:messaging,view')
        ->name('calls.answer');
    Route::post('/calls/{call}/decline', [CallController::class, 'decline'])
        ->middleware('page.permission:messaging,view')
        ->name('calls.decline');
    Route::post('/calls/{call}/end', [CallController::class, 'end'])
        ->middleware('page.permission:messaging,view')
        ->name('calls.end');
    Route::post('/calls/{call}/signal', [CallController::class, 'signal'])
        ->middleware('page.permission:messaging,view')
        ->name('calls.signal');
    Route::get('/calls/{call}/signals', [CallController::class, 'signals'])
        ->middleware('page.permission:messaging,view')
        ->name('calls.signals');

    // Partner logos (company logos uploaded at registration / lead creation)
    Route::post('/logo-partner/upload', [CrmLogoPartnerController::class, 'upload'])
        ->middleware('page.permission:leads,edit')
        ->name('logo-partner.upload');
    Route::delete('/logo-partner/{id}', [CrmLogoPartnerController::class, 'destroy'])
        ->middleware('page.permission:leads,edit')
        ->name('logo-partner.destroy');

});

/*
|--------------------------------------------------------------------------
| Public meeting-request responses (reached from the client email)
|--------------------------------------------------------------------------
| No auth: the client clicks a link in the email to confirm, decline or ask
| for a reschedule. Responses land in the CRM feedback panel.
*/
Route::prefix('meeting-request')->name('meeting-request.')->middleware('throttle:40,1')->group(function () {
    Route::get('{token}', [MeetingRequestController::class, 'show'])->name('show');
    Route::get('{token}/confirm', [MeetingRequestController::class, 'confirm'])->name('confirm');
    Route::get('{token}/decline', [MeetingRequestController::class, 'decline'])->name('decline');
    Route::get('{token}/reschedule', [MeetingRequestController::class, 'rescheduleForm'])->name('reschedule.form');
    Route::post('{token}/reschedule', [MeetingRequestController::class, 'reschedule'])->name('reschedule');
});

/*
|--------------------------------------------------------------------------
| Public partner registration form (reached from the CRM email button)
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:40,1')->group(function () {
    Route::get('crm/registration/{token}', [CrmRegistrationController::class, 'show'])
        ->name('crm.registration.show');
    Route::post('crm/registration/{token}', [CrmRegistrationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('crm.registration.store');
});

/*
|--------------------------------------------------------------------------
| Inbound email capture
|--------------------------------------------------------------------------
| Point an inbound-mail service (Mailgun, Postmark, Cloudflare Email Routing,
| …) at this endpoint with CRM_EMAIL_INBOUND_KEY as the path secret. Payload:
| { from, from_name?, subject?, body }.
*/
Route::post('crm/email/inbound/{key}', [CrmEmailController::class, 'inbound'])
    ->middleware('throttle:60,1')
    ->name('crm.email.inbound');
