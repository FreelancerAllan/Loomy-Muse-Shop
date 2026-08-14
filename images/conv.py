from bs4 import BeautifulSoup
import vtracer


def convert_png_to_svg(
    input_img="logo2-removebg-preview.png",
    output_svg="logo2_final.svg",
    target_width=482,
    target_height=72,
):
  print(f"Tracing {input_img} into vector paths...")

  temp_svg = "temp_trace.svg"

  # Using colormode='binary' or 'color' with specific hierarchical settings
  vtracer.convert_image_to_svg_py(
      input_img,
      temp_svg,
      colormode="color",
      hierarchical="stacked",
      mode="spline",
      filter_speckle=4,  # Cleans up invisible background pixel noise
  )

  print("Adjusting SVG dimensions and background...")
  with open(temp_svg, "r", encoding="utf-8") as f:
    soup = BeautifulSoup(f, "xml")

  svg_tag = soup.find("svg")
  if svg_tag:
    # Set exact requested dimensions & viewBox
    svg_tag["width"] = str(target_width)
    svg_tag["height"] = str(target_height)
    svg_tag["viewBox"] = f"0 0 {target_width} {target_height}"

    # OPTIONAL: If your logo background converted to a giant annoying solid box,
    # uncomment the lines below to inject a clean white background rectangle:
    # rect = soup.new_tag('rect', width="100%", height="100%", fill="white")
    # svg_tag.insert(0, rect)

  with open(output_svg, "w", encoding="utf-8") as f:
    f.write(str(soup))

  print(f"Success! Open {output_svg} in your browser.")


if __name__ == "__main__":
  convert_png_to_svg()