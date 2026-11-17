const nodemailer = require('nodemailer');

const success = { ok: true, message: 'Thanks! Your message has been sent.' };
const cleanLine = (value, max) => typeof value === 'string'
  ? value.replace(/[\x00-\x1f\x7f]/g, ' ').trim().slice(0, max) : '';

module.exports = async function handler(req, res) {
  res.setHeader('Cache-Control', 'no-store');
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    return res.status(405).json({ ok: false, message: 'Method not allowed.' });
  }

  const body = typeof req.body === 'string'
    ? Object.fromEntries(new URLSearchParams(req.body)) : (req.body || {});
  if (body.website) return res.status(200).json(success);
  const started = Number(body.started);
  if (started > 0 && Date.now() / 1000 - started < 3) return res.status(200).json(success);

  const name = cleanLine(body.name, 100);
  const email = cleanLine(body.email, 254);
  const subject = cleanLine(body.subject, 150);
  const message = typeof body.message === 'string' ? body.message.trim() : '';
  if (!name || !email || !message) {
    return res.status(422).json({ ok: false, message: 'Please fill in your name, email and message.' });
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || /[\r\n]/.test(body.email)) {
    return res.status(422).json({ ok: false, message: 'Please enter a valid email address.' });
  }
  if (message.length < 10 || message.length > 5000) {
    return res.status(422).json({ ok: false, message: 'Please use between 10 and 5000 characters for your message.' });
  }

  const { SMTP_HOST, SMTP_USER, SMTP_PASS, SMTP_FROM, CONTACT_RECIPIENT } = process.env;
  if (!SMTP_HOST || !SMTP_USER || !SMTP_PASS || !SMTP_FROM || !CONTACT_RECIPIENT) {
    return res.status(503).json({ ok: false, message: 'Please email me directly at semihbceylan@gmail.com.' });
  }
  const port = Number(process.env.SMTP_PORT || 587);
  const transport = nodemailer.createTransport({
    host: SMTP_HOST, port, secure: port === 465, requireTLS: port !== 465,
    auth: { user: SMTP_USER, pass: SMTP_PASS },
    connectionTimeout: 10000, greetingTimeout: 10000, socketTimeout: 15000
  });
  try {
    await transport.sendMail({
      from: { name: 'semihbceylan.com', address: SMTP_FROM },
      to: CONTACT_RECIPIENT, replyTo: email,
      subject: `Portfolio contact: ${subject || `Message from ${name}`}`,
      text: `New portfolio contact\nName: ${name}\nEmail: ${email}\nSubject: ${subject || '(none)'}\n\n${message}`
    });
    return res.status(200).json(success);
  } catch {
    // Never return provider errors or credentials to visitors.
    return res.status(502).json({ ok: false, message: 'Sorry, the message could not be sent right now. Please email me directly at semihbceylan@gmail.com.' });
  } finally {
    transport.close();
  }
};
