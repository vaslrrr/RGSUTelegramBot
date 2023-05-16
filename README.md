# RGSUTelegramBot

	Information bot for RGSU, that can show shedule, sending notifications, show custom information pages, sending newsletter
	
# Install and Run
	Firstly you need to install Apache+PHP+MariaDB, then install db.sql file to your database
	
	After you need change settings in /include/database_engine.php:
	private static $DB_HOST = "SET YOUR SERVER HOST";
    private static $DB_USER = "SET DB USER NAME";
    private static $DB_PASS = "SET DB PASSWORD";
    private static $DB_NAME = "SET DB NAME";
	
	Than you need to change settings in /include/settings.php:

	const SECRET_KEY = "SOME RANDOM PHRASE";

	const BOT_TOKEN = "TOKEN BOT FROM Telegram's @BotFather";

	const WRAPPER_LINK = "LINK TO YOUR SITE/bot_control.php";


	const LOGIN = 'ADMIN LOGIN';
	const PASSWORD = 'ADMIN PASS';
	
	After that you need to run script in /bot_controller/webhook_activator.php, you must run that through terminal
	
	At the end you need setup cron jobs and set:
	/bot_controller/shedule_updater.php once each day at about 21h
	/bot_ontroller/notif_sender.php every 5 minutes
	
	That's all!
	