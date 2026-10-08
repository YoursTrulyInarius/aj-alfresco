update the readme $ErrorActionPreference = 'Stop'

$phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
if ($phpCommand) {
    $phpPath = $phpCommand.Source
} else {
    $xamppRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    $phpPath = Join-Path $xamppRoot 'php\php.exe'
}
$runnerPath = Join-Path $PSScriptRoot 'run_reminders.php'
$taskName = 'AJ Alfresco Reminder Check'

if (-not (Test-Path $phpPath)) {
    throw "PHP executable not found: $phpPath"
}
if (-not (Test-Path $runnerPath)) {
    throw "Reminder runner not found: $runnerPath"
}

$userId = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$startBoundary = (Get-Date).Date.AddDays(1).AddHours(8).ToString('yyyy-MM-ddTHH:mm:ss')
$subscription = '<QueryList><Query Id="0" Path="System"><Select Path="System">*[System[Provider[@Name=''Microsoft-Windows-Kernel-General''] and EventID=1]]</Select></Query></QueryList>'
$escapedSubscription = [System.Security.SecurityElement]::Escape($subscription)
$escapedUserId = [System.Security.SecurityElement]::Escape($userId)
$escapedPhpPath = [System.Security.SecurityElement]::Escape($phpPath)
$escapedRunnerPath = [System.Security.SecurityElement]::Escape('"' + $runnerPath + '"')

$taskXml = @"
<?xml version="1.0" encoding="UTF-16"?>
<Task version="1.4" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task">
  <RegistrationInfo>
    <Description>Checks rent and contract reminders when Windows time changes and once daily.</Description>
  </RegistrationInfo>
  <Triggers>
    <CalendarTrigger>
      <StartBoundary>$startBoundary</StartBoundary>
      <Enabled>true</Enabled>
      <ScheduleByDay><DaysInterval>1</DaysInterval></ScheduleByDay>
    </CalendarTrigger>
    <EventTrigger>
      <Enabled>true</Enabled>
      <Subscription>$escapedSubscription</Subscription>
    </EventTrigger>
  </Triggers>
  <Principals>
    <Principal id="Author">
      <UserId>$escapedUserId</UserId>
      <LogonType>InteractiveToken</LogonType>
      <RunLevel>LeastPrivilege</RunLevel>
    </Principal>
  </Principals>
  <Settings>
    <MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>
    <DisallowStartIfOnBatteries>false</DisallowStartIfOnBatteries>
    <StopIfGoingOnBatteries>false</StopIfGoingOnBatteries>
    <StartWhenAvailable>true</StartWhenAvailable>
    <Enabled>true</Enabled>
    <ExecutionTimeLimit>PT10M</ExecutionTimeLimit>
    <Priority>7</Priority>
  </Settings>
  <Actions Context="Author">
    <Exec>
      <Command>$escapedPhpPath</Command>
      <Arguments>$escapedRunnerPath</Arguments>
      <WorkingDirectory>$PSScriptRoot</WorkingDirectory>
    </Exec>
  </Actions>
</Task>
"@

[xml]$taskXml | Out-Null
$scheduler = New-Object -ComObject Schedule.Service
$scheduler.Connect()
$rootFolder = $scheduler.GetFolder('\')
$rootFolder.RegisterTask($taskName, $taskXml, 6, $userId, $null, 3, $null) | Out-Null

Write-Output "Registered '$taskName' for $userId."
Write-Output 'Reminder checks run on Windows time changes and daily at 8:00 AM.'