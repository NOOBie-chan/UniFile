# UniFile 🚀

Send files between devices on the same WiFi. No login, no cloud, no limits.

UniFile generates a 6-digit code + IP. Share the code with anyone on your network and drop files. Everything is stored temporarily on your server.

## ✨ Features
- **No Account**: Works instantly. No signup.
- **P2P-ish via Server**: Upload from Device A, download on Device B with code
- **Session Codes**: Each session gets a unique IP + 6-digit PIN
- **Multi-device**: See all users in the session live
- **Dark Mode**: Auto-switches with your system
- **Lightweight**: Pure PHP + Vanilla JS. Works on free hosting

## 🛠️ Tech Stack
- **Backend**: PHP 8.0+
- **Frontend**: HTML, CSS, Vanilla JavaScript
- **Storage**: JSON files + /sessions folders
- **Hosting**: Tested on InfinityFree

## ⚡ Quick Setup on InfinityFree
1.  **Upload Files**: Upload everything to `/htdocs`
2.  **Create Folder**: Create `/htdocs/sessions` and set permissions to `755`
3.  **Done**: Visit `yourdomain.infinityfreeapp.com/upload.php`

## 🔧 Local Setup
```bash
git clone https://github.com/your-username/unifile.git
cd unifile
php -S localhost:8000
