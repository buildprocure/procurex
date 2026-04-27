import os
import textwrap
import requests


GITHUB_TOKEN = os.environ["GITHUB_TOKEN"]
OWNER = os.environ["GITHUB_OWNER"]
REPO = os.environ["GITHUB_REPO"]
PR_NUMBER = int(os.environ["PR_NUMBER"])

API = "https://api.github.com"

HEADERS = {
    "Authorization": f"Bearer {GITHUB_TOKEN}",
    "Accept": "application/vnd.github+json",
}


def gh_get(path, accept=None):
    headers = dict(HEADERS)
    if accept:
        headers["Accept"] = accept

    response = requests.get(f"{API}{path}", headers=headers, timeout=30)
    response.raise_for_status()
    return response


def gh_post(path, payload):
    response = requests.post(
        f"{API}{path}",
        headers=HEADERS,
        json=payload,
        timeout=30,
    )

    if response.status_code >= 400:
        print("GitHub API error")
        print("Status:", response.status_code)
        print("URL:", f"{API}{path}")
        print("Response:", response.text)
        print("Accepted permissions:", response.headers.get("X-Accepted-GitHub-Permissions"))
        print("OAuth scopes:", response.headers.get("X-OAuth-Scopes"))

    response.raise_for_status()
    return response.json()


def get_pr():
    return gh_get(f"/repos/{OWNER}/{REPO}/pulls/{PR_NUMBER}").json()


def get_pr_files():
    return gh_get(f"/repos/{OWNER}/{REPO}/pulls/{PR_NUMBER}/files").json()


def get_pr_diff():
    return gh_get(
        f"/repos/{OWNER}/{REPO}/pulls/{PR_NUMBER}",
        accept="application/vnd.github.v3.diff",
    ).text


def classify_pr(files):
    names = [f["filename"].lower() for f in files]

    docs_ext = (".md", ".txt", ".rst", ".adoc")
    code_ext = (".php", ".js", ".ts", ".tsx", ".jsx", ".py", ".sql")

    docs_only = all(
        n.endswith(docs_ext) or n.startswith("docs/") or "readme" in n
        for n in names
    )

    has_code = any(n.endswith(code_ext) for n in names)
    has_tests = any("test" in n or "spec" in n for n in names)
    has_ci = any(".github/workflows" in n or "azure-pipelines" in n for n in names)
    has_docker = any("docker" in n or "compose" in n for n in names)

    if docs_only:
        pr_type = "documentation"
    elif has_ci:
        pr_type = "ci_cd"
    elif has_docker:
        pr_type = "docker_deployment"
    elif has_code:
        pr_type = "code"
    else:
        pr_type = "mixed"

    return {
        "type": pr_type,
        "docs_only": docs_only,
        "has_code": has_code,
        "has_tests": has_tests,
        "has_ci": has_ci,
        "has_docker": has_docker,
    }


def build_review(pr, files, diff):
    classification = classify_pr(files)

    additions = sum(f.get("additions", 0) for f in files)
    deletions = sum(f.get("deletions", 0) for f in files)

    changed_files = "\n".join(
        f"- `{f['filename']}` ({f.get('status')}, +{f.get('additions', 0)}/-{f.get('deletions', 0)})"
        for f in files
    )

    warnings = []
    suggestions = []

    if classification["type"] == "documentation":
        suggestions.append(
            "Verify the updated documentation matches the actual project setup, runtime, deployment process, and repository structure."
        )
        if any("readme" in f["filename"].lower() for f in files):
            suggestions.append(
                "Consider renaming `readme.txt` to `README.md` for better GitHub rendering if the team agrees."
            )

    if classification["has_code"] and not classification["has_tests"]:
        warnings.append(
            "Code files changed but no obvious test/spec file changed. Confirm existing tests cover the affected behavior."
        )

    if classification["has_ci"]:
        warnings.append(
            "CI/CD workflow files changed. Review secrets, permissions, branch behavior, and deployment impact carefully."
        )

    if classification["has_docker"]:
        warnings.append(
            "Docker or compose files changed. Validate image names, ports, env files, networks, and runtime assumptions."
        )

    if not warnings:
        warnings.append("None identified from the available PR metadata and changed-file summary.")

    if not suggestions:
        suggestions.append("Review the diff for correctness, maintainability, and edge cases before approval.")

    review = f"""
## 🤖 BuildProcure Agent Review

### Summary
PR #{PR_NUMBER} updates **{len(files)} file(s)** with **{additions} additions** and **{deletions} deletions**.

**Detected PR type:** `{classification["type"]}`

### Changed Files
{changed_files}

### Senior Engineer Assessment
This review is based on the PR metadata and changed files available to the workflow. Please treat it as an assistant review, not a replacement for human approval.

### Warnings
{chr(10).join(f"- {w}" for w in warnings)}

### Suggestions
{chr(10).join(f"- {s}" for s in suggestions)}

### Test Review
{"No automated tests appear necessary for a documentation-only change." if classification["docs_only"] else "Confirm test coverage is appropriate for the changed behavior."}

### Approval Recommendation
{"Approve with comments" if not classification["has_code"] else "Needs human review before approval"}

---
Triggered by `/agent-review`.
"""
    return textwrap.dedent(review).strip()


def post_comment(body):
    return gh_post(
        f"/repos/{OWNER}/{REPO}/issues/{PR_NUMBER}/comments",
        {"body": body},
    )


def main():
    pr = get_pr()
    files = get_pr_files()
    diff = get_pr_diff()

    review = build_review(pr, files, diff)
    post_comment(review)


if __name__ == "__main__":
    main()