# Erick Palma — Portfolio

Bilingual (English / Spanish) portfolio website for **Erick Palma — Web Developer & Business Solutions**.

A static site (plain HTML + JavaScript, no build step), hosted free on **GitHub Pages**.
Projects and contact info live in two JSON files, so updating the site means editing those files.

## Features

- Hero, services, projects, process, about and contact sections
- EN / ES language toggle (auto-detects Spanish browsers, remembers the choice)
- Project cards with screenshot, description, problem/result, tools and links (live site, demo, video)
- "Featured" projects show as full-width cards
- Contact form via [Web3Forms](https://web3forms.com) (free)
- Responsive, mobile-first

## Structure

```
index.html           The whole site
data/projects.json   Your projects
data/settings.json   Contact info, photo, "projects launched" number, Web3Forms key
images/              Project screenshots and your profile photo
```

## Updating the site

You can edit everything right on github.com: open a file, click the pencil icon, make the change and click **Commit changes**. The live site updates in about a minute.

**Add a photo:** open the `images` folder on GitHub → **Add file → Upload files** → drag the image in → **Commit changes**.
Use simple file names without spaces, e.g. `bros-tacos.jpg`. Best size for project screenshots: 1600×1000 (wide).

**Add or edit a project:** edit `data/projects.json`. Each project looks like this:

```json
{
  "id": "p5",
  "order": 5,
  "featured": false,
  "title": "Project name",
  "title_es": "Nombre en español (optional)",
  "tag_en": "Restaurant · Website",
  "tag_es": "Restaurante · Sitio web",
  "desc_en": "Short description.",
  "desc_es": "Descripción corta.",
  "problem_en": "", "problem_es": "",
  "result_en": "", "result_es": "",
  "stack": ["PHP", "Square"],
  "live_url": "https://example.com",
  "demo_url": "",
  "video_url": "",
  "image": "example.jpg"
}
```

- `order` sets the position (1 = first). `featured: true` makes it a big full-width card.
- `image` is the file name of a photo in the `images` folder. Leave it `""` for a colored card.
- Optional `image_mobile`: a phone screenshot (tall) shown in a phone frame over the main photo, e.g. `"image_mobile": "bros-tacos-mobile.png"`.
- Leave a link `""` to hide its button. Leave a `_es` field `""` to show the English text in Spanish too.
- Separate projects with a comma, and keep the `[` `]` around the whole list.

**Contact info:** edit `data/settings.json`. Leave a field `""` to hide it. `photo` is a file name in `images`.
`web3forms_key` is the free access key from web3forms.com that makes the contact form email you.

## Run locally

The page loads its JSON files, so open it through a small local server instead of double-clicking it:

```bash
python -m http.server 8000
# open http://localhost:8000
```
