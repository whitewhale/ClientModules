This module provides EMS integration for LiveWhale and LiveWhale calendar.

Features:

- Automatic syncing of EMS events into LiveWhale (via SOAP or REST).
- Configurable syncing rules to restrict by status, group type, and event type.
- Mapping of EMS event types to corresponding LiveWhale event types.

Configuration:

- Open your global.config.php.
- Add the WSDL url for your EMS installation (for SOAP API).
	$_LW->REGISTERED_APPS['ems']['custom']['wsdl']='https://yourschool.emscloudservice.com/emsapi/service.asmx?wsdl';
- Add the REST url for your EMS installation (for REST API).
	$_LW->REGISTERED_APPS['ems']['custom']['rest']='https://yourschool/EmsPlatform/api/v1';
- Configure the system path to the .pem file to enable optional SSL validation (SOAP). This can be tested with: curl -v --cacert file.pem --capath /path/to/certs "https://<server>.emscloudservice.com/"
	$_LW->REGISTERED_APPS['ems']['custom']['cafile']='/path/to/DigiCertSHA2SecureServerCA.pem';
- Configure the system path to the server's certificate directory for additional certs in the chain (SOAP).
	$_LW->REGISTERED_APPS['ems']['custom']['capath']='/etc/ssl/certs';
- Optionally add each EMS event type that should be mapped to a corresponding LiveWhale event type:
	$_LW->REGISTERED_APPS['ems']['custom']['event_types_map']=array(
		1=>'LiveWhale Event Type', // use: EMS event type ID => LiveWhale event type title
		2=>'Etc.'
	);
- Optionally configure the EMS statuses that events should be imported for:
	$_LW->REGISTERED_APPS['ems']['custom']['default_statuses']=array(
		1,
		2,
		3
	);
- Optionally configure the EMS group types that events should be imported for:
	$_LW->REGISTERED_APPS['ems']['custom']['default_group_types']=array(
		1,
		2,
		3
	);
- Optionally configure the EMS event types that events should be imported for:
	$_LW->REGISTERED_APPS['ems']['custom']['default_event_types']=array(
		1,
		2,
		3
	);
- Optionally configure EMS to import new events as hidden instead of live:
	$_LW->REGISTERED_APPS['ems']['custom']['hidden_by_default']=true;
- Optionally configure EMS to import UDFs:
	$_LW->REGISTERED_APPS['ems']['custom']['enable_udfs']=true;
- Optionally configure EMS to define a UDF as the source of tags to import and assign to LiveWhale events:
	$_LW->REGISTERED_APPS['ems']['custom']['udf_tags']='My UDF Title';

- Open your config.php.
- Add an entry to the CREDENTIALS array for your EMS credentials (username/password or client ID /secret):
	'EMS'=>array(
		'username'=>'myuser',
		'password'=>'mypassword'
	)

Instructions:

- Once installed and configured, visit the group editor for each LiveWhale group that corresponds to an EMS group.
- Select the EMS group that aligns with the group in question and save.
- Create a linked calendar in each LiveWhale group that has an EMS group assigned. The EMS url for that group will be displayed for reference.
- Save the calendar to import EMS events.

Requirements:

- PHP SOAP extension (if using SOAP API).

Note:

This module requires a contract with White Whale Web Services for use. Please contact support@livewhale.com for more information.
