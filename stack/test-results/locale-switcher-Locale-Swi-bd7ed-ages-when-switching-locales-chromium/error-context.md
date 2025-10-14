# Page snapshot

```yaml
- generic [ref=e3]:
  - link [ref=e5] [cursor=pointer]:
    - /url: /
    - img [ref=e6] [cursor=pointer]
  - generic [ref=e9]:
    - generic [ref=e10]:
      - generic [ref=e12]: Email
      - textbox "Email" [active] [ref=e13]
    - generic [ref=e14]:
      - generic [ref=e16]: Password
      - textbox "Password" [ref=e17]
    - generic [ref=e19]:
      - checkbox "Remember me" [ref=e20]
      - generic [ref=e21]: Remember me
    - generic [ref=e22]:
      - link "Forgot your password?" [ref=e23] [cursor=pointer]:
        - /url: http://localhost:8000/forgot-password
      - button "Log in" [ref=e24] [cursor=pointer]
```