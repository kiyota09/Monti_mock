Dear {{ $employeeName }},

Welcome to Monti Corporation!

Your employee account has been successfully created.

Employee ID: {{ $employeeId }}
Position: {{ $position }}
Department: {{ $department }}
Company Email: {{ $companyEmail }}
Temporary Password: {{ $tempPassword }}

@if($trainingStartDate)
Your training is scheduled to begin on {{ $trainingStartDate }}
@if($trainingStartTime)
at {{ $trainingStartTime }}
@endif
@if($trainingEndTime)
and end at {{ $trainingEndTime }}
@endif
.
@endif

You may use your company email to access the Employee Portal after activation.
Access the portal: {{ $portalUrl }}

If this email landed in your spam folder, please mark it as "Not spam" so you
receive future notices from the HR Department.

If you have any questions, please contact the HR Department.

Welcome to the team!
Monti HR Department
