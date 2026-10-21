# 07: AI Act

**Regulation (EU) 2024/1689.** It entered into force on 1 August 2024 and applies in
stages. The bans on prohibited practices have applied since February 2025, the rules
for general-purpose AI models since August 2025, and the **transparency
obligations (Art. 50) from 2 August 2026**. **Verify** the dates: the
Commission's 2025 "Digital Omnibus" proposal would postpone parts of the timeline.

## Does it apply to hakoo.eu?

**Not today.** The only machine-learning component is **tesseract.js OCR**,
which reads text from screenshots in the browser. It is minimal-risk and has no
AI Act obligations.

## When it would matter

| If we add... | Obligation |
|---|---|
| An AI chatbot (for example, a Discord bot answering with an LLM) | **Art. 50(1)**: users must be told they are talking to an AI, unless it is obvious |
| AI-generated images, video or audio shown as real ("deepfakes") | **Art. 50(4)**: disclose that the content is AI-generated or manipulated |
| AI-generated text published to inform the public on matters of public interest | **Art. 50(4)**: disclose it, unless it is human-reviewed with editorial responsibility |
| Emotion recognition or biometric categorisation | Transparency duties; some uses are prohibited. **Avoid.** |

AI features that process member data (for example, summarising Discord chats)
also trigger **GDPR**: legal basis, information to members, and data transfers to
the AI provider.

## Sources

- AI Act text: https://eur-lex.europa.eu/eli/reg/2024/1689/oj
- Commission AI Act page: https://digital-strategy.ec.europa.eu/en/policies/regulatory-framework-ai
