# My Portfolio Website

## 🚀 About the Project
This is my personal portfolio website showcasing my experience, projects and skills.

## 📂 Folder Structure
```bash
semihbceylan.com
│── images/
│   ├── logos/            # Company & school logos (stored locally)
│   ├── portfolio/        # Original project screenshots
│   │   └── thumbs/       # Optimized .webp thumbnails used on the site
│   └── portrait.webp     # Optimized hero photo (original: pp21.jpeg)
│── index.html
│── style.css
│── app.js                # Animations, mobile menu, contact form
│── contact.php           # Contact form mail handler
│── README.md
```

## 📜 Features
- Responsive design for all devices
- Experience, projects, skills, education, awards and languages sections
- Lazy-loaded images with reserved space (no layout shift while loading)
- Working contact form (PHP) with spam protection
- SEO and social share (Open Graph) tags

## 🌐 Deploying on cPanel (no shell needed)
1. Open **cPanel → File Manager → public_html**.
2. Upload `index.html`, `style.css`, `app.js`, `contact.php` and the `images/` folder.
3. Contact form setup:
   - In **cPanel → Email Accounts**, create `noreply@semihbceylan.com` (it only needs to exist).
   - In **cPanel → Email Deliverability**, make sure SPF and DKIM show as valid (click *Repair* if not).
     Without this, Gmail may put the messages in spam.
   - To change the recipient or sender, edit the settings at the top of `contact.php`.
4. Send a test message from the live site.

### Resume
Upload your CV as `resume.pdf` into `public_html`. The **Resume** button switches to it
automatically; until then it links to LinkedIn.

## 💻 Local preview
Open `index.html` in a browser. The contact form needs PHP, so test it on the live server
(or run `php -S localhost:8000` locally).

## 🚀 Live Demo
🔗 [View Portfolio](https://semihbceylan.com)
