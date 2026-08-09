FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------
# System dependencies
# ---------------------------------------------------------
RUN apt-get update && apt-get install -y \
    python3 python3-pip python3-venv \
    git wget curl \
    libgl1 libglib2.0-0 libsm6 libxrender1 libxext6 \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------
# Create ComfyUI environment
# ---------------------------------------------------------
WORKDIR /opt/comfyui
RUN python3 -m venv /opt/comfyui-venv
ENV PATH="/opt/comfyui-venv/bin:${PATH}" \
    HF_HOME=/opt/comfyui/cache/huggingface \
    HUGGINGFACE_HUB_CACHE=/opt/comfyui/cache/huggingface \
    TRANSFORMERS_CACHE=/opt/comfyui/cache/huggingface \
    TORCH_HOME=/opt/comfyui/cache/torch \
    XDG_CACHE_HOME=/opt/comfyui/cache/xdg
RUN mkdir -p /opt/comfyui/cache/huggingface /opt/comfyui/cache/torch /opt/comfyui/cache/xdg /opt/comfyui/models /opt/comfyui/llm

# ---------------------------------------------------------
# Upgrade pip
# ---------------------------------------------------------
RUN pip install --upgrade pip

# ---------------------------------------------------------
# Install PyTorch with CUDA 13.0 (Blackwell / GB200)
# ---------------------------------------------------------
RUN pip install torch torchvision --index-url https://download.pytorch.org/whl/cu130

# ---------------------------------------------------------
# Clone ComfyUI into a clean working directory
# ---------------------------------------------------------
RUN rm -rf /opt/comfyui/* /opt/comfyui/.[!.]* /opt/comfyui/..?* 2>/dev/null || true
RUN git clone https://github.com/comfyanonymous/ComfyUI.git /opt/comfyui-src && cp -a /opt/comfyui-src/. /opt/comfyui/

# ---------------------------------------------------------
# Install ComfyUI dependencies
# ---------------------------------------------------------
RUN pip install -r requirements.txt

# ---------------------------------------------------------
# Add startup entrypoint
# ---------------------------------------------------------
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ---------------------------------------------------------
# Expose ComfyUI port
# ---------------------------------------------------------
EXPOSE 8188

# ---------------------------------------------------------
# Run ComfyUI
# ---------------------------------------------------------
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["--listen", "0.0.0.0"]
