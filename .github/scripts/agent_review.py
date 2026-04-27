import os
import requests


GITHUB_TOKEN = os.environ["GITHUB_TOKEN"]
OWNER = os.environ["GITHUB_OWNER"]
REPO = os.environ["GITHUB_REPO"]
PR_NUMBER = int(os.environ["PR_NUMBER"])

MCP_AGENT_REVIEW_URL = os.getenv(
    "MCP_AGENT_REVIEW_URL",
    "http://143.198.64.132:3011/agent-review",
)

API = "https://api.github.com"

HEADERS = {
    "Authorization": f"Bearer {GITHUB_TOKEN}",
    "Accept": "application/vnd.github+json",
}


def post_comment(body):
    response = requests.post(
        f"{API}/repos/{OWNER}/{REPO}/issues/{PR_NUMBER}/comments",
        headers=HEADERS,
        json={"body": body},
        timeout=30,
    )

    if response.status_code >= 400:
        print("GitHub API error")
        print("Status:", response.status_code)
        print("Response:", response.text)

    response.raise_for_status()
    return response.json()


def call_mcp_agent_review():
    payload = {
        "repo_name": REPO,
        "pr_number": PR_NUMBER,
    }

    response = requests.post(
        MCP_AGENT_REVIEW_URL,
        json=payload,
        timeout=120,
    )

    if response.status_code >= 400:
        print("MCP agent review error")
        print("Status:", response.status_code)
        print("Response:", response.text)

    response.raise_for_status()
    return response.json()


def main():
    result = call_mcp_agent_review()
    review_markdown = result["review_markdown"]
    post_comment(review_markdown)


if __name__ == "__main__":
    main()