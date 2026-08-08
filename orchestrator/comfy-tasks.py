def build_workflow(post_id: int) -> dict:
    return {
        "prompt": {
            "1": {
                "class_type": "CLIPTextEncode",
                "inputs": {
                    "text": f"Generate an image for WordPress post {post_id}"
                }
            },
            "2": {
                "class_type": "KSampler",
                "inputs": {
                    "seed": 12345,
                    "steps": 20,
                    "cfg": 8.0,
                    "sampler_name": "euler",
                    "scheduler": "normal",
                    "denoise": 1.0,
                    "model": "sd15",
                    "positive": ["1"],
                    "negative": []
                }
            }
        }
    }
