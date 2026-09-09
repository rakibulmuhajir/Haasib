"""Generate QR assets for the mockup's hotel map searches."""
from pathlib import Path
from reportlab.graphics.barcode.qr import QrCodeWidget
from reportlab.graphics.shapes import Drawing
from reportlab.graphics import renderSVG

assets = Path(__file__).parent / "assets"
assets.mkdir(exist_ok=True)
for name, query in [
    ("makkah", "Makkah+Clock+Royal+Tower+A+Fairmont+Hotel"),
    ("madinah", "Dar+Al+Iman+InterContinental+Madinah"),
]:
    code = QrCodeWidget("https://www.google.com/maps/search/?api=1&query=" + query)
    drawing = Drawing(100, 100)
    drawing.add(code)
    renderSVG.drawToFile(drawing, str(assets / (name + "-map.svg")))
