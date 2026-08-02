from pathlib import Path

from PIL import Image, ImageOps


OUTPUT_DIR = Path(__file__).parent
frames = []

for path in sorted(OUTPUT_DIR.glob("[0-9][0-9]-*.png")):
    with Image.open(path) as source:
        frame = ImageOps.contain(source.convert("RGB"), (1280, 720))
        canvas = Image.new("RGB", (1280, 720), "#111827")
        canvas.paste(
            frame,
            ((canvas.width - frame.width) // 2, (canvas.height - frame.height) // 2),
        )
        frames.append(canvas)

if not frames:
    raise SystemExit("No browser frames found.")

frames[0].save(
    OUTPUT_DIR / "haasib-umrah-e2e-recording.gif",
    save_all=True,
    append_images=frames[1:],
    duration=1400,
    loop=0,
    optimize=True,
)
