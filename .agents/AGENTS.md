# Mandatory Versioning, Dual-Branch Deployment & Packaging Directive

> ⚠️ **STRICT USER DIRECTIVE**: For every single edit, update, bug fix, or feature addition made in any project or plugin in this workspace, you MUST ALWAYS automatically:
> 1. Document the update in the `README.md` file under the `## 📋 Changelog` section with the current date and version.
> 2. Bump/increment the version number of the project or plugin (e.g. in `version.php` / `package.json` / badge).
> 3. **Auto-deploy to Production and Staging**:
>    - MUST push to BOTH branches on GitHub: `git push origin master` AND `git push origin master:staging`. Pushing to `master` deploys to Production; pushing to `staging` deploys to Staging (`80.225.79.61`).
> 4. **Package ZIP Files**:
>    - MUST run `powershell -ExecutionPolicy Bypass -File "c:\Users\mahmo\OneDrive - Energy & Water Academy\Work\Repo\package_moodle_plugins.ps1"` to update plugin ZIP files in `packaged_plugins/`.

