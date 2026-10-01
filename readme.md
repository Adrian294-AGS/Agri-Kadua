# How to Contribute (Beginner Friendly)

Hi! This guide shows you how to add your work to this project.
No Git experience needed. Just follow the steps in order.

---

## What you'll do

1. Make your own copy of this project (called a "fork")
2. Download it to your computer
3. Make your changes
4. Upload your changes
5. Ask the owner to add them

That's it. Let's go.

---

## Step 1 — Make your own copy (Fork)

1. Open this project on GitHub.
2. Click the **Fork** button at the top right.
3. Wait a few seconds. GitHub makes a copy under your name.

✅ You now have your own copy.

---

## Step 2 — Download your copy (Clone)

Open your terminal (Command Prompt, PowerShell, or Terminal).

Type this and press Enter:

```bash
git clone https://github.com/YOUR-USERNAME/REPO-NAME.git
```

⚠️ Replace:
- `YOUR-USERNAME` with your GitHub username
- `REPO-NAME` with the project name

Then go into the folder:

```bash
cd REPO-NAME
```

---

## Step 3 — Tell Git where the original project is

Copy this and press Enter:

```bash
git remote add upstream https://github.com/OWNER-USERNAME/REPO-NAME.git
```

⚠️ Replace `OWNER-USERNAME` with the project owner's GitHub username.

Check it worked:

```bash
git remote -v
```

You should see two lines: `origin` and `upstream`. Good.

---

## Step 4 — Make a new branch

**Never work on `main`.** Always make a new branch.

```bash
git checkout -b my-work
```

You can name it anything, like `my-work` or `login-page`.

---

## Step 5 — Make your changes

Open the files and edit them like normal. Save your work.

---

## Step 6 — Save your changes (Commit)

Type these one at a time:

```bash
git add .
git commit -m "Describe what you did"
```

Example messages:
- `Add login page`
- `Fix button color`
- `Update README`

---

## Step 7 — Upload your changes (Push)

```bash
git push origin my-work
```

⚠️ Use the same branch name you made in Step 4.

If it asks for a password, use a **Personal Access Token** (not your GitHub password).
👉 [How to make one](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens)

---

## Step 8 — Ask the owner to add your work (Pull Request)

1. Go to the **original project** on GitHub (not your fork).
2. You'll see a green button: **Compare & pull request**. Click it.
3. Write a short title and description.
4. Click **Create pull request**.

✅ Done! Now wait for the owner to review.

---

## Step 9 — If the owner asks for changes

Just edit your files again, then:

```bash
git add .
git commit -m "Fix based on feedback"
git push origin my-work
```

Your Pull Request updates automatically. No need to make a new one.

---

## Step 10 — After your work is added (Merged)

Once the owner merges your work, update your copy:

```bash
git checkout main
git pull upstream main
git push origin main
```

---

## Starting your next task

Every time you want to add something new, do this:

```bash
git checkout main
git pull upstream main
git checkout -b new-task-name
```

Then repeat Steps 5 to 8.

---

## Common Problems

**"Permission denied" or "403"**
You're trying to upload to the owner's project. Upload to **your fork** instead:
```bash
git push origin my-work
```

**"Not a git repository"**
You're in the wrong folder. Go into the project folder:
```bash
cd REPO-NAME
```

**"Updates were rejected"**
Get the latest first:
```bash
git pull upstream main
```

**It keeps asking for a password**
Use a Personal Access Token instead of your password.

---

## The Rules

1. **Never work on `main`** — always make a new branch.
2. **One task per branch.**
3. **Write clear commit messages.**
4. **Don't upload `node_modules` or `venv` folders.**
5. **Test your work before asking for a Pull Request.**

---

## All Commands in One Place

Copy-paste ready. Replace the CAPITAL words with your info.

### First time only

```bash
git clone https://github.com/YOUR-USERNAME/REPO-NAME.git
cd REPO-NAME
git remote add upstream https://github.com/OWNER-USERNAME/REPO-NAME.git
git remote -v
```

### Every time you contribute

```bash
git checkout main
git pull upstream main
git checkout -b my-work
# ...edit your files...
git add .
git commit -m "Describe what you did"
git push origin my-work
# ...then open a Pull Request on GitHub...
```

### If the owner asks for changes

```bash
git add .
git commit -m "Fix based on feedback"
git push origin my-work
```

### After your work is merged

```bash
git checkout main
git pull upstream main
git push origin main
```

### Install project dependencies

Ask the owner which one to use:

```bash
npm install                       # for JavaScript projects
pip install -r requirements.txt   # for Python projects
composer install                  # for PHP projects
bundle install                    # for Ruby projects
```

---

## Still stuck?

Ask in the group chat. Don't struggle alone. 🙂