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
│── api/contact.js        # Vercel serverless SMTP contact handler
│── scripts/build.js      # Copies only public assets into public/
│── vercel.json           # Vercel build and routing configuration
│── contact.php           # Legacy PHP handler (not deployed on Vercel)
│── README.md
```

## 📜 Features
- Responsive design for all devices
- Experience, projects, skills, education, awards and languages sections
- Lazy-loaded images with reserved space (no layout shift while loading)
- SMTP contact form (Node.js on Vercel) with validation and honeypot spam protection
- SEO and social share (Open Graph) tags

## 🌐 Deploying on Vercel
1. Import `semihbceylan/semihbceylan.com` using Vercel's GitHub integration.
2. Use the **Other** framework preset, `npm run build`, and output directory `public`.
3. Set **Production Branch** to `main`. Pushes to `main` automatically deploy to production;
   other branches receive preview deployments.
4. Add the environment variables listed in `.env.example` in Vercel project settings.
   Store `SMTP_PASS` as a sensitive variable, never in Git. SMTP port 587 uses required STARTTLS.
5. Deploy. Add the custom domain separately when ready to switch DNS from the old host.

The build copies only HTML, CSS, browser JavaScript, images and an optional resume.
PHP source, environment files and diagnostic scripts are never published as static assets.
`/contact.php` is rewritten to `/api/contact` for older clients.

## Legacy cPanel hosting
For the PHP version, set the form action back to `contact.php` and configure SMTP
environment variables on your server (PHP does not automatically load `.env.local`).
1. Open **cPanel → File Manager → public_html**.
2. Upload `index.html`, `style.css`, `app.js`, `contact.php` and the `images/` folder.
3. Contact form setup:
   - In **cPanel → Email Accounts**, create `noreply@semihbceylan.com` (it only needs to exist).
   - In **cPanel → Email Deliverability**, make sure SPF and DKIM show as valid (click *Repair* if not).
     Without this, Gmail may put the messages in spam.
   - Configure the sender, recipient and SMTP credentials through server environment variables.
4. Send a test message from the live site.

### Resume
Upload your CV as `resume.pdf` into `public_html`. The **Resume** button switches to it
automatically; until then it links to LinkedIn.

## 💻 Local preview
Run `npm ci`, `npm test` and `npm run build`. For the serverless contact form, run
`npx vercel dev` with SMTP variables in an ignored `.env.local` file.

## 🚀 Live Demo
🔗 [View Portfolio](https://semihbceylan.com)
